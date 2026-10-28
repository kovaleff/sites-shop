<?php

declare(strict_types=1);

use App\Orchid\Screens\Category\CategoryEditScreen;
use App\Orchid\Screens\Category\CategoryListScreen;
use App\Orchid\Screens\Conversation\ConversationListScreen;
use App\Orchid\Screens\Conversation\ConversationShowScreen;
use App\Orchid\Screens\Examples\ExampleActionsScreen;
use App\Orchid\Screens\Examples\ExampleCardsScreen;
use App\Orchid\Screens\Examples\ExampleChartsScreen;
use App\Orchid\Screens\Examples\ExampleFieldsAdvancedScreen;
use App\Orchid\Screens\Examples\ExampleFieldsScreen;
use App\Orchid\Screens\Examples\ExampleGridScreen;
use App\Orchid\Screens\Examples\ExampleLayoutsScreen;
use App\Orchid\Screens\Examples\ExampleScreen;
use App\Orchid\Screens\Examples\ExampleTextEditorsScreen;
use App\Orchid\Screens\Message\MessageListScreen;
use App\Orchid\Screens\Message\MessageShowScreen;
use App\Orchid\Screens\PlatformScreen;
use App\Orchid\Screens\Role\RoleEditScreen;
use App\Orchid\Screens\Role\RoleListScreen;
use App\Orchid\Screens\Site\SiteEditScreen;
use App\Orchid\Screens\Site\SiteListScreen;
use App\Orchid\Screens\SubscriptionPayment\SubscriptionPaymentListScreen;
use App\Orchid\Screens\SubscriptionPayment\SubscriptionPaymentShowScreen;
use App\Orchid\Screens\User\UserEditScreen;
use App\Orchid\Screens\User\UserListScreen;
use App\Orchid\Screens\User\UserProfileScreen;
use Illuminate\Support\Facades\Route;
use Tabuna\Breadcrumbs\Trail;

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the need "dashboard" middleware group. Now create something great!
|
*/

// Main
Route::screen('/main', PlatformScreen::class)
    ->name('platform.main');

// Platform > Profile
Route::screen('profile', UserProfileScreen::class)
    ->name('platform.profile')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Profile'), route('platform.profile')));

// Platform > System > Users > User
Route::screen('users/{user}/edit', UserEditScreen::class)
    ->name('platform.systems.users.edit')
    ->breadcrumbs(fn (Trail $trail, $user) => $trail
        ->parent('platform.systems.users')
        ->push($user->name, route('platform.systems.users.edit', $user)));

// Platform > System > Users > Create
Route::screen('users/create', UserEditScreen::class)
    ->name('platform.systems.users.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.users')
        ->push(__('Create'), route('platform.systems.users.create')));

// Platform > System > Users
Route::screen('users', UserListScreen::class)
    ->name('platform.systems.users')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Users'), route('platform.systems.users')));

// Platform > System > Roles > Role
Route::screen('roles/{role}/edit', RoleEditScreen::class)
    ->name('platform.systems.roles.edit')
    ->breadcrumbs(fn (Trail $trail, $role) => $trail
        ->parent('platform.systems.roles')
        ->push($role->name, route('platform.systems.roles.edit', $role)));

// Platform > System > Roles > Create
Route::screen('roles/create', RoleEditScreen::class)
    ->name('platform.systems.roles.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.systems.roles')
        ->push(__('Create'), route('platform.systems.roles.create')));

// Platform > System > Roles
Route::screen('roles', RoleListScreen::class)
    ->name('platform.systems.roles')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push(__('Roles'), route('platform.systems.roles')));

// Platform > Marketplace > Sites
Route::screen('marketplace/sites', SiteListScreen::class)
    ->name('platform.marketplace.sites')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Объявления', route('platform.marketplace.sites')));

// Platform > Marketplace > Sites > Site
Route::screen('marketplace/sites/{site}/edit', SiteEditScreen::class)
    ->where('site', '[0-9]+')
    ->name('platform.marketplace.sites.edit')
    ->breadcrumbs(fn (Trail $trail, $site) => $trail
        ->parent('platform.marketplace.sites')
        ->push($site->title, route('platform.marketplace.sites.edit', $site)));

// Platform > Marketplace > Categories > Create
Route::screen('marketplace/categories/create', CategoryEditScreen::class)
    ->name('platform.marketplace.categories.create')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.marketplace.categories')
        ->push('Новая тематика', route('platform.marketplace.categories.create')));

// Platform > Marketplace > Categories > Edit
Route::screen('marketplace/categories/{category}/edit', CategoryEditScreen::class)
    ->name('platform.marketplace.categories.edit')
    ->breadcrumbs(fn (Trail $trail, $category) => $trail
        ->parent('platform.marketplace.categories')
        ->push($category->name, route('platform.marketplace.categories.edit', $category)));

// Platform > Marketplace > Categories
Route::screen('marketplace/categories', CategoryListScreen::class)
    ->name('platform.marketplace.categories')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Тематики', route('platform.marketplace.categories')));

// Platform > Marketplace > Conversations
Route::screen('marketplace/conversations', ConversationListScreen::class)
    ->name('platform.marketplace.conversations')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Диалоги', route('platform.marketplace.conversations')));

// Platform > Marketplace > Conversations > Conversation
Route::screen('marketplace/conversations/{conversation}', ConversationShowScreen::class)
    ->where('conversation', '[0-9]+')
    ->name('platform.marketplace.conversations.show')
    ->breadcrumbs(fn (Trail $trail, $conversation) => $trail
        ->parent('platform.marketplace.conversations')
        ->push('Диалог #'.$conversation->id, route('platform.marketplace.conversations.show', $conversation)));

// Platform > Marketplace > Messages
Route::screen('marketplace/messages', MessageListScreen::class)
    ->name('platform.marketplace.messages')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Сообщения', route('platform.marketplace.messages')));

// Platform > Marketplace > Messages > Message
Route::screen('marketplace/messages/{message}', MessageShowScreen::class)
    ->where('message', '[0-9]+')
    ->name('platform.marketplace.messages.show')
    ->breadcrumbs(fn (Trail $trail, $message) => $trail
        ->parent('platform.marketplace.messages')
        ->push('Сообщение #'.$message->id, route('platform.marketplace.messages.show', $message)));

// Platform > Marketplace > Payments
Route::screen('marketplace/payments', SubscriptionPaymentListScreen::class)
    ->name('platform.marketplace.payments')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Платежи', route('platform.marketplace.payments')));

// Platform > Marketplace > Payments > Payment
Route::screen('marketplace/payments/{subscriptionPayment}', SubscriptionPaymentShowScreen::class)
    ->where('subscriptionPayment', '[0-9]+')
    ->name('platform.marketplace.payments.show')
    ->breadcrumbs(fn (Trail $trail, $subscriptionPayment) => $trail
        ->parent('platform.marketplace.payments')
        ->push('Платёж #'.$subscriptionPayment->id, route('platform.marketplace.payments.show', $subscriptionPayment)));

// Example...
Route::screen('example', ExampleScreen::class)
    ->name('platform.example')
    ->breadcrumbs(fn (Trail $trail) => $trail
        ->parent('platform.index')
        ->push('Example Screen'));

Route::screen('/examples/form/fields', ExampleFieldsScreen::class)->name('platform.example.fields');
Route::screen('/examples/form/advanced', ExampleFieldsAdvancedScreen::class)->name('platform.example.advanced');
Route::screen('/examples/form/editors', ExampleTextEditorsScreen::class)->name('platform.example.editors');
Route::screen('/examples/form/actions', ExampleActionsScreen::class)->name('platform.example.actions');

Route::screen('/examples/layouts', ExampleLayoutsScreen::class)->name('platform.example.layouts');
Route::screen('/examples/grid', ExampleGridScreen::class)->name('platform.example.grid');
Route::screen('/examples/charts', ExampleChartsScreen::class)->name('platform.example.charts');
Route::screen('/examples/cards', ExampleCardsScreen::class)->name('platform.example.cards');

// Route::screen('idea', Idea::class, 'platform.screens.idea');
