<?php

namespace App\Providers\Filament;

use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Navigation\MenuItem;
use Filament\Support\Colors\Color;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Http\Middleware\Authenticate;
use App\Filament\Widgets\CustomAccountWidget;
use App\Filament\Widgets\InviteLinkWidget;
use App\Filament\Widgets\UsersStatsWidget;
use App\Filament\Widgets\TopCitiesChart;
use App\Filament\Widgets\TopNeighborhoodsPieChart;
use App\Filament\Widgets\GenderPieChart;
use App\Filament\Widgets\Concern01PieChart;
use App\Filament\Widgets\Concern02PieChart;
use App\Filament\Widgets\AgeGroupPieChart;
use Filament\Forms\Components\Placeholder;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use App\Filament\Auth\CustomLogin;
use App\Filament\Auth\PasswordReset\CustomRequestPasswordReset;
use App\Filament\Pages\DirectRegistrations;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(CustomLogin::class)
            ->passwordReset(CustomRequestPasswordReset::class)
            ->sidebarFullyCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->favicon(asset('favicon.ico'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                DirectRegistrations::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                CustomAccountWidget::class,
                InviteLinkWidget::class,
                UsersStatsWidget::class,
                TopCitiesChart::class,
                TopNeighborhoodsPieChart::class,
                GenderPieChart::class,
                Concern01PieChart::class,
                Concern02PieChart::class,
                AgeGroupPieChart::class,
            ])
            ->bootUsing(function () {
                Section::configureUsing(function (Section $field) {
                    $field->translateLabel();
                });

                Field::configureUsing(function (Field $field) {
                    $field->translateLabel();
                });

                TextInput::configureUsing(function (TextInput $field) {
                    $field->translateLabel();
                });

                TextColumn::configureUsing(function (TextColumn $field) {
                    $field->translateLabel();
                });

                ImageColumn::configureUsing(function (ImageColumn $field) {
                    $field->translateLabel();
                });

                ToggleColumn::configureUsing(function (ToggleColumn $field) {
                    $field->translateLabel();
                });

                Placeholder::configureUsing(function (Placeholder $field) {
                    $field->translateLabel();
                });

                IconColumn::configureUsing(function (IconColumn $field) {
                    $field->translateLabel();
                });
            })
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->userMenuItems([
                'logout' => MenuItem::make()->label(__('Logout'))
            ]);
    }
}
