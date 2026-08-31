<?php

namespace App\Filament\Pages;

use App\Support\Settings as SettingsStore;
use App\Support\SettingsRegistry;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Admin-editable application settings page.
 */
class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configurações';

    protected static ?string $title = 'Configurações';

    protected static ?int $navigationSort = 12;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->isAdmin() ?? false;
    }

    private static function toFieldName(string $configKey): string
    {
        return str_replace('.', '__', $configKey);
    }

    private static function toConfigKey(string $fieldName): string
    {
        return str_replace('__', '.', $fieldName);
    }

    public function mount(): void
    {
        $state = [];
        foreach (SettingsStore::all() as $configKey => $value) {
            $state[self::toFieldName($configKey)] = $value;
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $defs = SettingsRegistry::definitions();
        $sections = [];

        foreach (SettingsRegistry::groups() as $group => $heading) {
            $fields = [];

            foreach ($defs as $configKey => $def) {
                if ($def['group'] !== $group) {
                    continue;
                }

                $name = self::toFieldName($configKey);

                $field = match ($def['type']) {
                    SettingsRegistry::TYPE_BOOL => Toggle::make($name),
                    SettingsRegistry::TYPE_INT => TextInput::make($name)->numeric()->required(),
                    SettingsRegistry::TYPE_SELECT => Select::make($name)->options($def['options'] ?? []),
                    SettingsRegistry::TYPE_TEXT => Textarea::make($name)->rows(6)->columnSpanFull(),
                    default => TextInput::make($name)->maxLength(255),
                };

                $field = $field->label($def['label']);

                if (isset($def['help'])) {
                    $field = $field->helperText($def['help']);
                }

                $fields[] = $field;
            }

            if ($fields !== []) {
                $sections[] = Section::make($heading)->schema($fields)->columns(2)->extraAttributes(['class' => 'items-end']);
            }
        }

        return $schema->components($sections)->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $values = [];
        foreach ($state as $fieldName => $value) {
            $values[self::toConfigKey($fieldName)] = $value;
        }

        // Settings::set() ignores anything outside the registry (allowlist).
        SettingsStore::set($values);

        Notification::make()
            ->title('Configurações salvas.')
            ->success()
            ->send();
    }
}
