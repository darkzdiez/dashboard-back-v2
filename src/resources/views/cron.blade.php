{!! "<" !!}{!! "?php" !!}
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\DB;

@foreach ($crons as $cron)
// {{ $cron->description }}
$schedule->{{ $cron->method }}('{{ $cron->command }}')
    ->cron('{{ $cron->minute }} {{ $cron->hour }} {{ $cron->day }} {{ $cron->month }} {{ $cron->day_of_week }}')
    ->onSuccess(function (Stringable $output) {
        DB::table('cron_log')->insert([
            'cron_id' => {{ $cron->id }},
            'run_at' => \Carbon\Carbon::now(),
            'run_status' => 'success',
            'output' => $output,
            'error' => '',
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
        ]);
    })
    ->onFailure(function (Stringable $output) {
        DB::table('cron_log')->insert([
            'cron_id' => {{ $cron->id }},
            'run_at' => \Carbon\Carbon::now(),
            'run_status' => 'failure',
            'output' => $output,
            'error' => '',
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
        ]);
    });

@endforeach
