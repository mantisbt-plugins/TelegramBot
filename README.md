# MantisBT TelegramBot Plugin

[![Join the chat at https://gitter.im/mantisbt-plugins/TelegramBot](https://badges.gitter.im/mantisbt-plugins/TelegramBot.svg)](https://gitter.im/mantisbt-plugins/TelegramBot?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Overview
--------
This is a bot for the Telegram messenger.

Screenshots
-----------

![alt text](doc/Firs_screen.png)
![alt text](doc/Telegram_client_validate_link_screen.png)
![alt text](doc/MantisBT_Telegram-Client_redirect_after_confirmation.png)
![alt text](doc/Telegram_Client_send_text.png)
![alt text](doc/Telegram_Client_sample_message_.png)
![alt text](doc/Telegram_Client_sample_note_message.png)

Features
--------
- Report bugs (v. >= 1.1);
- Attach files to bugs;
- Send comments to bugs;
- Receive notification when (v. >= 1.2): 
    - creating an bug;
    - change the status of the bug;
    - adding a comment to the bug;
    - mention of the user in the commentary.
- Respond to chat alerts about events using the built-in function "Reply to message". The answer will be added as a comment to the bug (v. >= 1.3);
- Support SOCKS5 proxy server ( Requires curl >= 7.21.7 );
- Two ways of getting updates from Telegram: webhook and long polling (v. >= 2.0);
- Guided issue creation covering every field of the report form (v. >= 2.0):
    - custom fields of all MantisBT types, including the ones defined by third party cfdef files;
    - the required fields are asked first, then the issue can be created right away or the optional fields filled in;
    - inline navigation: go back to any answered step, skip any optional field;
    - an inline calendar for the date fields with the month, year and twelve year views;
    - required fields and value formats are validated right in the dialog.

Download
--------
Please download the stable version.
(https://github.com/mantisbt-plugins/TelegramBot/releases/latest)


How to install
--------------

1. Copy TelegramBot folder into plugins folder.
2. Open Mantis with browser.
3. Log in as administrator.
4. Go to Manage -> Manage Plugins.
5. Find TelegramBot in the list.
6. Click Install.
7. Click TelegramBot link
8. Follow the instructions.

Getting updates: Webhook or Script
----------------------------------

Telegram delivers updates to a bot in one of two ways, chosen in the plugin settings
(*Manage -> Manage Plugins -> TelegramBot -> Settings*, option **Getting updates method**).

**Webhook** - Telegram servers connect to your MantisBT instance themselves, so it has to be
reachable from the Internet over HTTPS with a certificate signed by a trusted CA. Updates arrive
instantly and nothing has to be scheduled.

**Script** (long polling) - MantisBT asks Telegram for updates itself, over an outgoing connection
only. Use it when publishing MantisBT on the Internet is not possible. Add the script shown on the
settings page to your scheduler, for example:

```
* * * * * /usr/bin/php <mantisbt>/plugins/TelegramBot/scripts/telegram_get_updates.php >/dev/null 2>&1
```

A single run keeps a connection to Telegram open and processes updates as soon as they arrive, so
the schedule above is not a polling interval - it only restarts the script for the next minute.
Two settings control the timing, both on the settings page in the Script mode:

- **Long polling timeout** (25 s by default) - how long Telegram holds the connection while there
  are no updates. It has to stay below the server response timeout, otherwise curl gives up before
  Telegram answers.
- **Run time** (55 s by default) - how long a single run works before exiting. Keep it below the
  interval between runs; a run started while another one is still working exits immediately.
  Setting it to 0 makes the script poll once and exit.

Only one instance runs at a time - Telegram rejects parallel getUpdates calls with 409 Conflict.
The page *Telegram API status* shows when the script was last started, which is the way to tell
that the scheduler entry actually works. Note that both methods are mutually exclusive: while a
webhook is set, getUpdates returns 409 Conflict, so switching to Script deletes the webhook.

For the Script mode the URL of your MantisBT instance must be known: in CLI it cannot be derived
from the request, and the bot puts it into the links it sends. Set `$g_path` in `config_inc.php`,
or fill the URL field on the settings page if MantisBT is published under a different name for
external users.

Supported Versions
------------------

- MantisBT 2.14 to 2.26 - supported in plugin version up to 1.5.x
- MantisBT 2.26 and higher - supported in plugin version 1.6 and higher
- Only https ssl certificate signed trusted ca is supported for MantisBT ( In the near future, a self-signed https certificate will be supported. )
