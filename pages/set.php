<?php

$token   = '413412311:AAEl_ZH1U9UF0HQjvpLBB9ZROMBlrLOZRP0';
$botname = 'mantis_profi_bot';

//require __DIR__ . '/vendor/autoload.php';

$t_tg   = new Longman\TelegramBot\Telegram( $token, $botname );
$result = $t_tg->setWebhook( 'https://sd.sibprofi.ru/sdtest/plugin.php?page=TelegramBot/hook' );

echo 'complite';
