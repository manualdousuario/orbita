<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Comments\CommentResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Services\CommentService;
use App\Services\ReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Moderation inbox for user-submitted reports.
 */
class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Denúncias';

    protected static ?string $modelLabel = 'denúncia';

    protected static ?string $pluralModelLabel = 'denúncias';

    protected static ?int $navigationSort = 8;

    /** @var array<string, string> */
    protected static array $targetTypes = [
        'post' => 'Post',
        'comment' => 'Comentário',
    ];

    public static function getNavigationBadge(): ?string
    {
        $unread = Report::query()->whereNull('read_at')->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('read_at')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state === null ? 'Não lida' : 'Lida')
                    ->color(fn ($state): string => $state === null ? 'warning' : 'gray'),
                TextColumn::make('reportable_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::$targetTypes[$state] ?? $state),
                TextColumn::make('target_excerpt')
                    ->label('Conteúdo denunciado')
                    ->getStateUsing(fn (Report $record): string => self::targetExcerpt($record))
                    ->wrap(),
                TextColumn::make('target_author')
                    ->label('Autor do conteúdo')
                    ->getStateUsing(fn (Report $record): ?string => self::targetAuthor($record))
                    ->placeholder('—'),
                TextColumn::make('target_post')
                    ->label('Post')
                    ->getStateUsing(fn (Report $record): ?string => self::targetPostTitle($record))
                    ->limit(40)
                    ->placeholder('—')
                    ->url(fn (Report $record): ?string => self::targetUrl($record))
                    ->openUrlInNewTab(),
                TextColumn::make('reporter.username')->label('Reportado por')->searchable()->sortable(),
                TextColumn::make('reason')->label('Motivo')->wrap()->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('reportable_type')->label('Tipo')->options(self::$targetTypes),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('mark_read')
                    ->label('Marcar como lida')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (Report $record): bool => ! $record->isRead())
                    ->action(fn (Report $record) => app(ReportService::class)->markRead($record, (int) auth()->id())),
                Action::make('mark_unread')
                    ->label('Marcar como não lida')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->visible(fn (Report $record): bool => $record->isRead())
                    ->action(fn (Report $record) => app(ReportService::class)->markUnread($record)),
                Action::make('view')
                    ->label('Ver no site')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Report $record): ?string => self::targetUrl($record))
                    ->visible(fn (Report $record): bool => self::targetUrl($record) !== null)
                    ->openUrlInNewTab(),
                Action::make('moderate')
                    ->label(fn (Report $record): string => $record->reportable_type === 'post' ? 'Editar post' : 'Editar comentário')
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->url(fn (Report $record): ?string => self::moderateUrl($record))
                    ->visible(fn (Report $record): bool => self::moderateUrl($record) !== null),
            ])
            ->bulkActions([
                BulkAction::make('mark_read')
                    ->label('Marcar como lidas')
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(function (Collection $records): void {
                        $records->each(fn (Report $report) => app(ReportService::class)->markRead($report, (int) auth()->id()));
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    private static function targetExcerpt(Report $report): string
    {
        $target = $report->target();

        return match (true) {
            $target instanceof Post => Str::limit(trim((string) $target->content), 2000) ?: (string) $target->title,
            $target instanceof Comment => (string) $target->content,
            default => '(conteúdo removido)',
        };
    }

    private static function targetAuthor(Report $report): ?string
    {
        $user = $report->target()?->user;

        return $user?->display_name ?? $user?->username;
    }

    private static function targetPostTitle(Report $report): ?string
    {
        $target = $report->target();

        return match (true) {
            $target instanceof Post => (string) $target->title,
            $target instanceof Comment => $target->post?->title,
            default => null,
        };
    }

    /** Public URL of the reported content; a comment links to its anchor on the post. */
    private static function targetUrl(Report $report): ?string
    {
        $target = $report->target();

        if ($target instanceof Post) {
            return route('posts.show', ['hashid' => $target->hashid, 'slug' => $target->slug]);
        }

        if ($target instanceof Comment && $target->post !== null) {
            return app(CommentService::class)->permalinkFor($target, $target->post);
        }

        return null;
    }

    public static function canViewAny(): bool
    {
        return Filament::auth()->user()?->isStaff() ?? false;
    }

    /** Admin edit URL of the reported content (PostResource/CommentResource). */
    private static function moderateUrl(Report $report): ?string
    {
        $target = $report->target();

        return match (true) {
            $target instanceof Post => PostResource::getUrl('edit', ['record' => $target]),
            $target instanceof Comment => CommentResource::getUrl('edit', ['record' => $target]),
            default => null,
        };
    }
}
