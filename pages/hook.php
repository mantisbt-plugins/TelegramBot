<?php

$token   = '413412311:AAEl_ZH1U9UF0HQjvpLBB9ZROMBlrLOZRP0';
$botname = 'mantis_profi_bot';

current_user_set( 2 );
try {
    $t_tg = new \Longman\TelegramBot\Telegram( $token, $botname );

    $t_tg->setDownloadPath( '/tmp/' );

    $post = json_decode( Longman\TelegramBot\Request::getInput(), true );

    $t_update = new Longman\TelegramBot\Entities\Update( $post, $botname );

    $t_update_type = $t_update->getUpdateType();

    switch( $t_update_type ) {
        case 'message':
            $t_message      = $t_update->getMessage();
            $t_message_type = $t_message->getType();


            switch( $t_message_type ) {

                case 'command':
                    $t_command = $t_message->getCommand();

                    switch( $t_command ) {
                        case 'start':

                            $t_signup_keyboard = new \Longman\TelegramBot\Entities\InlineKeyboard( array() );
                            $t_signup_keyboard->addRow( [
                                                      'text' => 'Зарегистрироваться',
                                                      'url'  => config_get_global( 'path' ) . plugin_page( 'registred_page', TRUE ) . '&telegram_user_id=' . $t_message->getChat()->getId() . '&bot_name=' . $t_tg->getBotUsername()
                            ] );
                            $data_signup       = [
                                                      'chat_id'      => $t_message->getChat()->getId(),
                                                      'text'         => 'Перейдите по ссылке',
                                                      'reply_markup' => $t_signup_keyboard,
//                                                      'reply_to_message_id' => $t_message->getMessageId()
                            ];

                            Longman\TelegramBot\Request::sendMessage( $data_signup );

                            break;
                    }
                    break;
                case 'photo':
                case 'document':
                    $t_file = $t_message->getDocument();

                    $inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

                    $t_per_page   = null;
                    $t_bug_count  = null;
                    $t_page_count = null;

                    $t_custom_filter = filter_create_assigned_to_unresolved( 0, 2 );
//            $t_custom_filter = filter_get_default();

                    $t_bugs = filter_get_bug_rows( $f_page_number, $t_per_page, $t_page_count, $t_bug_count, $t_custom_filter, 0, null, true );

                    foreach( $t_bugs as $t_bug ) {

                        $inline_keyboard->addRow( [ 'text' => $t_bug->id . ': ' . $t_bug->summary, 'callback_data' => $t_bug->id ] );
                    }

                    $data = [
                                              'chat_id'             => $t_message->getChat()->getId(),
                                              'text'                => 'Выбирите задачу',
                                              'reply_markup'        => $inline_keyboard,
                                              'reply_to_message_id' => $t_message->getMessageId()
                    ];

                    Longman\TelegramBot\Request::sendMessage( $data );
                    break;
                default :
                    break;
            }
            break;
        case 'callback_query':
            $t_callback_query = $t_update->getCallbackQuery();
            $t_data           = json_decode( $t_callback_query->getData(), TRUE );

            $t_orgl_message = $t_callback_query->getMessage()->getReplyToMessage();

            $t_content_type = $t_orgl_message->getType();

            switch( $t_content_type ) {

                case 'photo':
                    $t_content_photo = $t_orgl_message->getPhoto();
                    $t_file_orgl     = $t_content_photo[3];
                    break;

                case 'document':
                    $t_file_orgl = $t_orgl_message->getDocument();
            }

//            $t_file = $t_orgl_message->getDocument();

            $t_download = Longman\TelegramBot\Request::getFile( [ 'file_id' => $t_file_orgl->getFileId() ] );

            $t_file = $t_download->getResult();

            Longman\TelegramBot\Request::downloadFile( $t_file );

            $t_file_path = $t_tg->getDownloadPath() . $t_file->getFilePath();

            $t_file_for_attach = [
                                      'browser_upload' => FALSE,
                                      'tmp_name'       => $t_file_path,
                                      'name'           => $t_file_orgl->getFileName() == NULL ? $t_file->getFilePath() : $t_file_orgl->getFileName()
            ];

            $t_file_complite = file_add( $t_data, $t_file_for_attach );

            $t_per_page   = null;
            $t_bug_count  = null;
            $t_page_count = null;

            $t_custom_filter = filter_create_assigned_to_unresolved( 0, 2 );
//            $t_custom_filter = filter_get_default();

            $t_bugs = filter_get_bug_rows( $f_page_number, $t_per_page, $t_page_count, $t_bug_count, $t_custom_filter, 0, null, true );

            $inline_keyboard = new Longman\TelegramBot\Entities\InlineKeyboard( array() );

            $data1 = [
                                      'chat_id'    => $t_callback_query->getMessage()->getChat()->getId(),
                                      'message_id' => $t_callback_query->getMessage()->getMessageId(),
                                      'text'       => 'Файл загружен в задачу: ' . $t_data
            ];

            Longman\TelegramBot\Request::editMessageText( $data1 );
            break;
        default :
            break;
    }
} catch( Longman\TelegramBot\Exception\TelegramException $e ) {
    // Silence is golden!
    // log telegram errors
    // echo $e->getMessage();
}
