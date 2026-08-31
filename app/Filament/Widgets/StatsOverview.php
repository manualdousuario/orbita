<?php

namespace App\Filament\Widgets;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\QueueMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard overview stats widget.
 */
class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Usuários', (string) User::count())
                ->description('Total de usuários')
                ->color('primary'),
            Stat::make('Usuários banidos', (string) User::where('is_banned', true)->count())
                ->description('Contas banidas')
                ->color('danger'),
            // whereNull('version_of') excludes revision snapshots from every post count.
            Stat::make('Posts publicados', (string) Post::whereNull('version_of')->whereIn('status', ['published', 'closed'])->count())
                ->description('Posts visíveis')
                ->color('success'),
            Stat::make('Comentários visíveis', (string) Comment::whereNull('version_of')->where('status', 'visible')->count())
                ->description('Comentários ativos')
                ->color('info'),
            Stat::make('Posts hoje', (string) Post::whereNull('version_of')->whereDate('created_at', today())->count())
                ->description('Criados hoje')
                ->color('warning'),
            Stat::make('Jobs pendentes', (string) QueueMetrics::pending())
                ->description('Aguardando processamento')
                ->color('warning'),
            Stat::make('Jobs com falha', (string) DB::table('failed_jobs')->count())
                ->description('Registrados em failed_jobs')
                ->color('danger'),
        ];
    }
}
