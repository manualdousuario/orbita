<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\ReactionType;
use App\Models\UserReaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manages reactions: toggling, summaries, and aggregate sync.
 */
class ReactionService
{
    public const TYPES = ['post', 'comment'];

    /** @var array<string, Collection<int, ReactionType>> memo per request: one component per comment would otherwise re-query */
    private array $typesByRole = [];

    /**
     * Active reaction types the given role is allowed to use, ordered for display.
     *
     * @return Collection<int, ReactionType>
     */
    public function typesForRole(string $role): Collection
    {
        return $this->typesByRole[$role] ??= ReactionType::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->filter(fn (ReactionType $t) => in_array($role, (array) $t->allowed_roles, true))
            ->values();
    }

    /**
     * Per-target reaction summaries for a batch of ids, in one query.
     *
     * @param  list<int>  $ids
     * @return array<int, array{reactions: array<string, int>, score: int, count: int}>
     */
    public function summariesFor(string $reactableType, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('user_reactions as ur')
            ->join('reaction_types as rt', 'ur.reaction_type_id', '=', 'rt.id')
            ->where('ur.reactable_type', $reactableType)
            ->whereIn('ur.reactable_id', $ids)
            ->get(['ur.reactable_id as target', 'rt.slug as slug', 'rt.score as score']);

        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->target;
            $out[$id]['reactions'][$row->slug] = ($out[$id]['reactions'][$row->slug] ?? 0) + 1;
            $out[$id]['score'] = ($out[$id]['score'] ?? 0) + (int) $row->score;
            $out[$id]['count'] = ($out[$id]['count'] ?? 0) + 1;
        }

        foreach ($ids as $id) {
            $out[(int) $id] ??= ['reactions' => [], 'score' => 0, 'count' => 0];
        }

        return $out;
    }

    /**
     * The caller's reaction slug per target id, in one query.
     *
     * @param  list<int>  $ids
     * @return array<int, string> target id => slug
     */
    public function userReactionsFor(?int $userId, string $reactableType, array $ids): array
    {
        if ($userId === null || $ids === []) {
            return [];
        }

        return DB::table('user_reactions as ur')
            ->join('reaction_types as rt', 'ur.reaction_type_id', '=', 'rt.id')
            ->where('ur.user_id', $userId)
            ->where('ur.reactable_type', $reactableType)
            ->whereIn('ur.reactable_id', $ids)
            ->pluck('rt.slug', 'ur.reactable_id')
            ->map(fn ($slug) => (string) $slug)
            ->all();
    }

    public function isValidType(string $reactableType): bool
    {
        return in_array($reactableType, self::TYPES, true);
    }

    public function usableType(string $slug, string $role): ?ReactionType
    {
        $type = ReactionType::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($type === null || ! in_array($role, (array) $type->allowed_roles, true)) {
            return null;
        }

        return $type;
    }

    public function findTarget(string $reactableType, int $reactableId): Post|Comment|null
    {
        return $reactableType === 'post'
            ? Post::find($reactableId)
            : Comment::find($reactableId);
    }

    /**
     * Toggles the user's reaction and returns the new summary.
     *
     * @return array{action: string, user_reaction: ?string, reactions: array<string, int>, score: int, count: int}
     */
    public function toggle(int $userId, string $reactableType, int $reactableId, ReactionType $type, Post|Comment $target): array
    {
        $existing = UserReaction::query()
            ->where('user_id', $userId)
            ->where('reactable_type', $reactableType)
            ->where('reactable_id', $reactableId)
            ->first();

        if ($existing !== null && (int) $existing->reaction_type_id === (int) $type->id) {
            $existing->delete();
            $action = 'removed';
            $userReaction = null;
        } elseif ($existing !== null) {
            $existing->update(['reaction_type_id' => $type->id]);
            $action = 'updated';
            $userReaction = $type->slug;
        } else {
            try {
                UserReaction::create([
                    'user_id' => $userId,
                    'reaction_type_id' => $type->id,
                    'reactable_type' => $reactableType,
                    'reactable_id' => $reactableId,
                ]);
            } catch (UniqueConstraintViolationException) {
                //
            }

            $action = 'added';
            $userReaction = $type->slug;
        }

        $this->syncReactionAggregates($reactableType, $reactableId, $target);

        return ['action' => $action, 'user_reaction' => $userReaction] + $this->summary($reactableType, $reactableId);
    }

    public function remove(int $userId, string $reactableType, int $reactableId): bool
    {
        $removed = UserReaction::query()
            ->where('user_id', $userId)
            ->where('reactable_type', $reactableType)
            ->where('reactable_id', $reactableId)
            ->delete() > 0;

        if ($removed) {
            $target = $this->findTarget($reactableType, $reactableId);
            if ($target !== null) {
                $this->syncReactionAggregates($reactableType, $reactableId, $target);
            }
        }

        return $removed;
    }

    /**
     * @return array{reactions: array<string, int>, score: int, count: int}
     */
    public function summary(string $reactableType, int $reactableId): array
    {
        $rows = DB::table('user_reactions as ur')
            ->join('reaction_types as rt', 'ur.reaction_type_id', '=', 'rt.id')
            ->where('ur.reactable_type', $reactableType)
            ->where('ur.reactable_id', $reactableId)
            ->get(['rt.slug as slug', 'rt.score as score']);

        $reactions = [];
        $score = 0;
        foreach ($rows as $row) {
            $reactions[$row->slug] = ($reactions[$row->slug] ?? 0) + 1;
            $score += (int) $row->score;
        }

        return [
            'reactions' => $reactions,
            'score' => $score,
            'count' => $rows->count(),
        ];
    }

    public function userReactionSlug(int $userId, string $reactableType, int $reactableId): ?string
    {
        return UserReaction::query()
            ->where('user_reactions.user_id', $userId)
            ->where('reactable_type', $reactableType)
            ->where('reactable_id', $reactableId)
            ->join('reaction_types as rt', 'user_reactions.reaction_type_id', '=', 'rt.id')
            ->value('rt.slug');
    }

    /**
     * Summary plus the caller's own reaction slug (null when anonymous).
     *
     * @return array{reactions: array<string, int>, score: int, count: int, user_reaction: ?string}
     */
    public function summaryForUser(string $reactableType, int $reactableId, ?int $userId): array
    {
        $userReaction = $userId !== null
            ? $this->userReactionSlug($userId, $reactableType, $reactableId)
            : null;

        return $this->summary($reactableType, $reactableId) + ['user_reaction' => $userReaction];
    }

    private function syncReactionAggregates(string $reactableType, int $reactableId, Post|Comment $target): void
    {
        if ($target instanceof Post) {
            DB::update(
                'UPDATE posts p
                 SET p.reaction_count = (
                         SELECT COUNT(*) FROM user_reactions ur
                         WHERE ur.reactable_type = ? AND ur.reactable_id = p.id
                     ),
                     p.reaction_score = (
                         SELECT COALESCE(SUM(rt.score), 0) FROM user_reactions ur
                         JOIN reaction_types rt ON ur.reaction_type_id = rt.id
                         WHERE ur.reactable_type = ? AND ur.reactable_id = p.id
                     )
                 WHERE p.id = ?',
                [$reactableType, $reactableType, (int) $target->id],
            );

            app(RankingService::class)->recalculatePostScore((int) $target->id);

            return;
        }

        DB::update(
            'UPDATE comments c
             SET c.reaction_count = (
                     SELECT COUNT(*) FROM user_reactions ur
                     WHERE ur.reactable_type = ? AND ur.reactable_id = c.id
                 ),
                 c.score = (
                     SELECT COALESCE(SUM(rt.score), 0) FROM user_reactions ur
                     JOIN reaction_types rt ON ur.reaction_type_id = rt.id
                     WHERE ur.reactable_type = ? AND ur.reactable_id = c.id
                 )
             WHERE c.id = ?',
            [$reactableType, $reactableType, (int) $target->id],
        );
    }
}
