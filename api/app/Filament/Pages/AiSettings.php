<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Set the Claude API key (stored encrypted) and choose the model that powers chat, classification,
 * and suggestions. Both resolve via SettingsService (DB setting → config/env fallback).
 */
class AiSettings extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string | UnitEnum | null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'AI Settings';

    protected static ?string $title = 'AI Settings';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.ai-settings';

    /** @var array<string,mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        // Never prefill the secret — the form shows only a masked hint; a blank key keeps the current one.
        $this->form->fill(['model' => app(SettingsService::class)->anthropicModel(), 'anthropic_api_key' => null]);
    }

    public function form(Schema $schema): Schema
    {
        $settings = app(SettingsService::class);

        return $schema
            ->components([
                Section::make('Claude model')
                    ->description('Which Claude model answers chat, classifies uploads, and generates suggestions.')
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        Select::make('model')
                            ->label('Model')
                            ->options(SettingsService::MODELS)
                            ->required()
                            ->native(false),
                    ]),
                Section::make('Anthropic API key')
                    ->description($settings->hasAnthropicKey()
                        ? "A key is set ({$settings->maskedHint()}, source: {$settings->source()}). Enter a new key to replace it."
                        : 'No key set. Paste your Anthropic API key to enable AI features.')
                    ->icon('heroicon-o-key')
                    ->schema([
                        TextInput::make('anthropic_api_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->placeholder('sk-ant-…')
                            ->helperText('Stored encrypted at rest. Leave blank to keep the current key.'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Test connection')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function () {
                    $key = $this->form->getState()['anthropic_api_key'] ?? null;
                    $result = app(SettingsService::class)->testAnthropicKey($key);
                    Notification::make()
                        ->title($result['ok'] ? 'Connection OK' : 'Connection failed')
                        ->body($result['message'])
                        ->{$result['ok'] ? 'success' : 'danger'}()
                        ->send();
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(SettingsService::class);

        $settings->setAnthropicModel($data['model'] ?? null);
        AuditLog::record('settings.model.updated', ['model' => $data['model'] ?? null]);

        if (filled($data['anthropic_api_key'] ?? null)) {
            $settings->setAnthropicKey($data['anthropic_api_key']);
            AuditLog::record('settings.anthropic_key.updated', ['source' => 'admin-page']);
        }

        // Clear the secret field; refresh the model value.
        $this->form->fill(['model' => $settings->anthropicModel(), 'anthropic_api_key' => null]);

        Notification::make()->title('AI settings saved')->success()->send();
    }
}
