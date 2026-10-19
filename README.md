# JooAg Shariff 8 – share buttons with privacy in mind

A Joomla system plugin for share buttons in the look of [Heise Shariff](https://github.com/heiseonline/shariff):
plain links, rendered on the server. A network only learns about the visit when the visitor clicks –
no external scripts, no cookies, no tracking, no jQuery, no web fonts.

Version 8 is the final, rebuilt version for **Joomla 5 and 6**. It runs without the
"Behaviour - Backward Compatibility" plugin, so sites can switch it off and move on to Joomla 6.

## Requirements

* Joomla 5.0 or later, Joomla 6
* PHP 8.1 or later

Joomla 3 and 4 sites keep JooAg Shariff 6.0.0. The installer of version 8 refuses older Joomla and PHP versions
with a message and changes nothing.

## Features

* Three ways to place the buttons, each switchable and combinable, each with its own position:
  1. automatically above and/or below Joomla articles, limited to categories and menu items
  2. "Show everywhere": above and/or below the output of any component, with menu item assignment
  3. short tag `{shariff}` in articles and custom modules ("Prepare Content": Yes), per place with
     `{shariff theme=grey}`, `{shariff orientation=vertical}`, `{shariff style=icon}` – also combined
* Exclude components (a `{shariff}` from a module is quietly removed there)
* Every settings tab explains itself (English and German)
* Services: Bluesky, Buffer, Copy link, Diaspora, Facebook, Flipboard, Info, LinkedIn, Mail, Mastodon (Fediverse),
  Pinterest, Print, Qzone, Reddit, Telegram, Threads, Threema, Tumblr, VK, Weibo, WhatsApp, X (Twitter), XING
* Themes color, grey, white; horizontal or vertical; icon and text, icon only, icon and count
* Texts and link titles in 25 languages (from Heise Shariff), following the page language or a fixed one
* Optional share counter for Buffer, Facebook (app ID and secret), Pinterest, Reddit, VK and XING: your server asks the
  networks through Joomla's own `com_ajax`, only for addresses on your domain(s), and keeps the numbers in Joomla's cache
* Same HTML and CSS classes as Heise Shariff (`.shariff`, `.shariff-button`, `.share_text`, `.share_count` …),
  so own CSS of existing sites keeps working; stylesheet and script can be switched off

## Update from older versions

* **4.x – 7.0.0:** update as usual (Joomla update or upload). All settings stay. The installer removes the
  former Shariff backend (`plugins/system/jooag_shariff/backend` with its bundled libraries), the old assets
  (`media/plg_jooag_shariff`) and old files. Services that no longer exist (Flattr, StumbleUpon, Pocket,
  Tencent Weibo, AddThis) are reported and no longer shown.
* **3.x:** note your settings, uninstall the old plugin, install version 8 and set it up again.
* The update server stays the same address, so every installed version finds the update.

## Changelog

### 8.0.1 (2026-10-19)
* Share counter only answers for addresses this site rendered buttons for: the page signs its address with the
  site's secret key, so the counter cannot be used to make the server query the networks for made-up addresses
* Share counter: one request asks the networks for at most about 10 seconds in all
* New setting "Button language": the language of the page (default) or a fixed one – idea of pull request #13
  by @thexmanxyz

### 8.0.0 (2026-10-19)
* Rebuilt for Joomla 5 and 6: services/provider.php, namespaced classes, Joomla events – no `J…` class aliases,
  runs without the Backward Compatibility plugin
* Buttons rendered on the server as plain links with inline SVG icons (Font Awesome Free 6.7.2); Heise Shariff
  JavaScript, jQuery and the Font Awesome web fonts are gone
* Share counter rebuilt with Joomla's HTTP client and cache; the public `backend/` folder with Guzzle, Laminas and
  `shariff.json` (Facebook secret) is gone
* All output escaped; short tag options are limited to known values (fixes attribute injection through `{shariff …}`)
* New services: Bluesky, Mastodon (via Share₂Fedi), Threads, Copy link; Twitter is now X
* Info button links to the chosen article again; the share language follows the current page language
* "Show everywhere" with both positions no longer repeats the page content
* Articles and "Show everywhere" have their own position each (above, below, both); the former shared
  "Output position" is taken over on update
* "Disallow components" lists the components of the website, third-party ones included (the old list showed
  the backend's component folders)
* Explanations on every settings tab; German translation of the settings

### Earlier versions
* 7.0.0 (2024, not released) – Joomla 5 compatibility attempt, Shariff backend 10.0.0
* 6.0.0 (2021) – Joomla 4 compatibility
* 5.0.0 (2019) – updated Shariff frontend and backend
* 4.0.0 (2018) – updated Shariff frontend and backend
* 3.x (2014 – 2017) – Joomla 3 plugin with Heise Shariff and Shariff backend

## Credits and licenses

* Plugin: GNU General Public License version 2 or later, see LICENSE – developed by
  [Joomla Agentur](https://joomla-agentur.de)
* Look, button texts and share addresses follow [Heise Shariff](https://github.com/heiseonline/shariff)
  (MIT License, Copyright (c) Heise Medien GmbH & Co. KG)
* Icons: [Font Awesome Free](https://fontawesome.com) 6.7.2 by Fonticons, Inc., CC BY 4.0
* Thanks to Heise for Shariff, to the Joomla community and the Joomla User Group Hamburg, and to David Jardin,
  Robert Deutz, Tobias Zulauf, Viktor Vogel and Yves Hoppe for help and support over the years
