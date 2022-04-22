<?php

namespace App\Http\Controllers;

use App\Modules\Telegram\Telegram;
use App\Telegram\Index;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $telegram = new Telegram();
        $update = $telegram->getWebhookUpdates();
        $start = new Index($telegram, $update);
        $start->init();
    }
}
