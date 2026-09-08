<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Concerns;

use Filament\Forms\Components\Select;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Livewire\Features\SupportTesting\Testable;
use RuntimeException;

final class AdminTables
{
    public static function of(Testable $component): HasTable
    {
        $instance = $component->instance();

        if (! $instance instanceof HasTable) {
            throw new RuntimeException('O componente montado não expõe uma tabela do Filament.');
        }

        return $instance;
    }

    public static function iconColumn(Testable $component, string $name): IconColumn
    {
        $column = self::of($component)->getTable()->getColumn($name);

        if (! $column instanceof IconColumn) {
            throw new RuntimeException("A coluna [{$name}] não é uma IconColumn.");
        }

        return $column;
    }

    public static function selectFilter(Testable $component, string $name): SelectFilter
    {
        $filter = self::of($component)->getTable()->getFilter($name);

        if (! $filter instanceof SelectFilter) {
            throw new RuntimeException("O filtro [{$name}] não é um SelectFilter.");
        }

        return $filter;
    }

    public static function filterSelect(Testable $component, string $filterName): Select
    {
        $group = self::of($component)
            ->getTableFiltersForm()
            ->getComponentByStatePath($filterName);

        $field = $group?->getChildSchema()?->getFlatFields()['value'] ?? null;

        if (! $field instanceof Select) {
            throw new RuntimeException("O filtro [{$filterName}] não expõe um campo Select.");
        }

        return $field;
    }
}
