<?php

namespace App\Filament\Pages;

use App\Models\YearlyDayPlan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class YearInAGlance extends Page implements HasActions, HasForms, HasInfolists
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'lucide-calendar-days';

    protected static string $view = 'filament.pages.year-in-a-glance';

    public $yearlyDayPlan;

    public bool $hidePastMonths = false;

    protected $listeners = [
        'refresh-data' => 'refreshData',
    ];

    public function refreshData(): void
    {
        $this->yearlyDayPlan = YearlyDayPlan::whereYear('date', now()->year)->get();
    }

    public function mount()
    {
        $this->yearlyDayPlan = YearlyDayPlan::whereYear('date', now()->year)->get();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->state([
                'months' => $this->getAllDays(),
            ])
            ->schema([
                RepeatableEntry::make('months')
                    ->hiddenLabel()
                    ->grid(12)
                    ->extraAttributes([
                        'class' => '[&_ul_div]:gap-0',
                    ])
                    ->schema([
                        TextEntry::make('month')
                            ->hiddenLabel()
                            ->alignCenter()
                            ->extraAttributes(fn ($state) => [
                                'class' => 'py-1 ' . $this->getMonthColor($state),
                            ]),
                        RepeatableEntry::make('days')
                            ->hiddenLabel()
                            ->extraAttributes([
                                'class' => '[&_ul_div]:gap-0'
                            ])
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                    TextEntry::make('day')
                                        ->hiddenLabel()
                                        ->columnSpan(1)
                                        ->alignCenter()
                                        ->tooltip(fn ($state) => $state->englishDayOfWeek)
                                        ->formatStateUsing(fn ($state) => $state->day)
                                        ->extraAttributes(fn ($state) => [
                                            'class' => 'border border-gray-200 px-3 py-1 ' . ($state->englishDayOfWeek === 'Saturday' || $state->englishDayOfWeek === 'Sunday' ? $this->getMonthColor($state->monthName) : ''),
                                        ]),
                                    TextEntry::make('body')
                                        ->hiddenLabel()
                                        ->columnSpan(5)
                                        ->limit(20)
                                        ->tooltip(fn ($state) => $state->body ?? '')
                                        ->formatStateUsing(fn ($state) => $state->body ?? '')
                                        ->extraAttributes(fn ($state) => [
                                            'class' => 'border border-gray-200 px-3 font-semibold ' . (isset($state['body']) ? 'py-1' : 'py-1.5'),
                                        ])
                                        ->suffixActions([
                                            fn ($state) => $state instanceof YearlyDayPlan
                                                ? Action::make('delete-plan')
                                                    ->icon('heroicon-o-trash')
                                                    ->extraAttributes([
                                                        'class' => 'text-gray-500',
                                                    ])
                                                    ->requiresConfirmation()
                                                    ->disabled(!$state instanceof YearlyDayPlan)
                                                    ->action(function (Action $action) {
                                                        $action->getComponent()->getState()->delete();
                                                        Notification::make()
                                                            ->title('Plan for the day deleted')
                                                            ->success()
                                                            ->send();
                                                        $this->dispatch('refresh-data');
                                                    })
                                                    : null,
                                            fn ($state) => $state instanceof YearlyDayPlan
                                                ? Action::make('copy-plan')
                                                    ->label('Copy plan to the other day')
                                                    ->icon('heroicon-o-document-duplicate')
                                                    ->extraAttributes([
                                                        'class' => 'text-gray-500',
                                                    ])
                                                    ->disabled(!$state instanceof YearlyDayPlan)
                                                    ->modalWidth(MaxWidth::Medium)
                                                    ->form([
                                                        TextInput::make('body')
                                                            ->label('Body')
                                                            ->default($state->body)
                                                            ->autofocus()
                                                            ->required(),
                                                        Toggle::make('multiple_days')
                                                            ->label('Multiple days')
                                                            ->default(false)
                                                            ->live(),
                                                        DatePicker::make('date')
                                                            ->label(fn (Get $get) => $get('multiple_days') ? 'Start Date' : 'Date')
                                                            ->required(),
                                                        DatePicker::make('end_date')
                                                            ->label('End Date')
                                                            ->default(Carbon::parse($state->date)->addDays(2)->toDateString())
                                                            ->hidden(fn (Get $get) => ! $get('multiple_days')),
                                                    ])
                                                    ->action(function (array $data) {
                                                        $this->runAction($data);
                                                    })
                                                    : null,
                                            fn ($state) => Action::make('edit-day')
                                                ->label('Plan for the day')
                                                ->modalWidth(MaxWidth::Medium)
                                                ->icon('heroicon-o-pencil-square')
                                                ->extraAttributes([
                                                    'class' => 'text-gray-500',
                                                ])
                                                ->form([
                                                    TextInput::make('body')
                                                        ->label('Body')
                                                        ->default($state->body ?? '')
                                                        ->autofocus()
                                                        ->required(),
                                                    Toggle::make('multiple_days')
                                                        ->label('Multiple days')
                                                        ->default(false)
                                                        ->live(),
                                                    DatePicker::make('date')
                                                        ->label(fn (Get $get) => $get('multiple_days') ? 'Start Date' : 'Date')
                                                        ->default($state->date ?? $state)
                                                        ->readOnly(),
                                                    DatePicker::make('end_date')
                                                        ->label('End Date')
                                                        ->hidden(fn (Get $get) => ! $get('multiple_days'))
                                                        ->default(Carbon::parse($state->date ?? $state)->addDays(2)->toDateString())
                                                        ->required(fn (Get $get) => $get('multiple_days') === true),
                                                ])
                                                ->action(function (array $data) {
                                                    if ($data['multiple_days']) {
                                                        $dates = CarbonPeriod::create($data['date'], $data['end_date']);
                                                        foreach ($dates as $date) {
                                                            YearlyDayPlan::updateOrCreate(
                                                                ['date' => $date->toDateString()],
                                                                ['body' => $data['body']],
                                                            );
                                                        }
                                                    } else {
                                                        YearlyDayPlan::updateOrCreate(
                                                            ['date' => $data['date']],
                                                            ['body' => $data['body']],
                                                        );
                                                    }
                                                    Notification::make()
                                                        ->title('Plan for the day updated')
                                                        ->success()
                                                        ->send();
                                                    $this->dispatch('refresh-data');
                                            }),
                                        ]),
                                ])
                            ])
                            ->contained(false),
                    ])
                    ->contained(false),
            ]);
    }

    private function runAction(array $data): void
    {
        if ($data['multiple_days']) {
            $dates = CarbonPeriod::create($data['date'], $data['end_date']);
            foreach ($dates as $date) {
                YearlyDayPlan::updateOrCreate(
                    ['date' => $date->toDateString()],
                    ['body' => $data['body']],
                );
            }
        } else {
            YearlyDayPlan::updateOrCreate(
                ['date' => $data['date']],
                ['body' => $data['body']],
            );
        }
        Notification::make()
            ->title('Plan for the day updated')
            ->success()
            ->send();
        $this->dispatch('refresh-data');
    }

    private function getMonthColor($month): string
    {
        return Cache::rememberForever(
            'month_colors',
            fn(): array => [
                'January' => 'bg-blue-100',
                'February' => 'bg-red-100',
                'March' => 'bg-purple-100',
                'April' => 'bg-emerald-100',
                'May' => 'bg-yellow-100',
                'June' => 'bg-sky-100',
                'July' => 'bg-rose-100',
                'August' => 'bg-violet-100',
                'September' => 'bg-cyan-100',
                'October' => 'bg-lime-100',
                'November' => 'bg-orange-100',
                'December' => 'bg-indigo-100',
            ],
        )[$month];
    }

    private function getMonths(): Collection
    {
        $months = Cache::rememberForever(
            'months',
            fn (): Collection => collect()
                ->range(1, 12)
                ->mapWithKeys(fn ($month): array => [Carbon::create(null, $month)->format('F') => $month]),
        );

        return $this->hidePastMonths
            ? $months->filter(fn ($month) => ! $this->monthAlreadyPassed($month))
            : $months;
    }

    private function getDaysInMonth($month, $monthName): array
    {
        return [
            'month' => $monthName,
            'days' => collect(CarbonPeriod::create(
                Carbon::create(null, $month)->startOfMonth(),
                Carbon::create(null, $month)->endOfMonth(),
            ))->mapWithKeys(fn ($day): array => [
                $day->day => [
                    'day' => $day,
                    'body' => $this->yearlyDayPlan->where('date', $day->toDateString())->first() ?? $day->toDateString(),
                ]
            ]),
        ];
    }

    private function getAllDays(): array
    {
        return $this->getMonths()
            ->map(fn ($month, $monthName) => $this->getDaysInMonth($month, $monthName))
            ->toArray();
    }

    private function monthAlreadyPassed($month): bool
    {
        return Carbon::create(month: $month)->month < now()->addMonth()->month;
    }
}
