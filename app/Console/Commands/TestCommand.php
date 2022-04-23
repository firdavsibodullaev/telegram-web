<?php

namespace App\Console\Commands;

use App\Modules\Telegram\Telegram;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $start = microtime(true);
        $this->info($start);

        $telegram = new Telegram();
        $i = 0;
        while ($i < 100) {
            $i++;
            $telegram->send('sendMessage', [
                'chat_id' => 287956415,
                'text' => "Test Message number: {$i}"
            ]);

            $this->info((memory_get_usage() / 1024 / 1024) . " MB");
        }

        $difference = number_format((microtime(true) - $start) * 1000, 2);
        $telegram->send('sendMessage', [
            'chat_id' => 287956415,
            'text' => "Duration: {$difference}"
        ]);
        return 0;
    }
}
