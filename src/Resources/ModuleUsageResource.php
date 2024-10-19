<?php

namespace HoceineEl\FilamentModularSubscriptions\Resources;

use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use HoceineEl\FilamentModularSubscriptions\Models\ModuleUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use HoceineEl\FilamentModularSubscriptions\Resources\ModuleUsageResource\Pages;

class ModuleUsageResource extends Resource
{
    protected static ?string $model = ModuleUsage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    public static function getNavigationLabel(): string
    {
        return __('filament-modular-subscriptions.resources.module_usage.name');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-modular-subscriptions.menu_group.subscription');
    }
    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('subscription_id')
                    ->relationship('subscription', 'id')
                    ->required()
                    ->label(__('filament-modular-subscriptions.fields.subscription_id')),
                Forms\Components\Select::make('module_id')
                    ->relationship('module', 'name')
                    ->required()
                    ->label(__('filament-modular-subscriptions.fields.module_id')),
                Forms\Components\TextInput::make('usage')
                    ->numeric()
                    ->required()
                    ->label(__('filament-modular-subscriptions.fields.usage')),
                Forms\Components\TextInput::make('pricing')
                    ->numeric()
                    ->required()
                    ->label(__('filament-modular-subscriptions.fields.pricing')),
                Forms\Components\DateTimePicker::make('calculated_at')
                    ->required()
                    ->default(now())
                    ->label(__('filament-modular-subscriptions.fields.calculated_at')),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subscription.id')
                    ->label(__('filament-modular-subscriptions.fields.subscription_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('module.name')
                    ->label(__('filament-modular-subscriptions.fields.module_id'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage')
                    ->label(__('filament-modular-subscriptions.fields.usage'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('pricing')
                    ->money('USD')
                    ->label(__('filament-modular-subscriptions.fields.pricing'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('calculated_at')
                    ->dateTime()
                    ->label(__('filament-modular-subscriptions.fields.calculated_at'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('module_id')
                    ->relationship('module', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModuleUsages::route('/'),
            'create' => Pages\CreateModuleUsage::route('/create'),
            'edit' => Pages\EditModuleUsage::route('/{record}/edit'),
        ];
    }
}
