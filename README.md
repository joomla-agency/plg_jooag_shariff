<h1>Joomla 3 Shariff Plugin</h1>
<h3>The 1-Click-Social-Button with privacy in mind</h3>
This Joomla 3 Plugin utilizes Heise Shariff Library to enable website users to share their favorite content without compromising their privacy.

<b>Help with translation:</b>
* https://www.transifex.com/jooag/plg_jooag_shariff/

<b>System Requirements:</b>
* Joomla 3.8+ 
* PHP 5.6, 7 or 7.1 (if you use the Counter)

<b>Plugin features:</b>
* Joomla Update integration
* Use this Plugins in your Modules and Articles via {shariff} shorttag
 * Now with shorttag parameter support e.g.  {shariff orientation=vertical theme=white}
* Languages: English, German, France (Contributed from: Simon | cinnk.com)
* Restrict the plugin execution to Menu Items or Content Categories
* Ordering for your Buttons
* Extended Shariff Backend Cache Handler
* Plugin settings for Themes, Orientation, Services and much more...

<b>Shariff Library features:</b>
* Services: Twitter, Facebook, GooglePlus, LinkedIn, Pinterest, Xing, Whatsapp, Addthis, tumblr
* Themes: Color, Grey, White
* Orientation: Horizontal, Vertical
* Responsive: Yes
* Shariff Languages: bg, de, en, es, fi, hr, hu, ja, ko, no, pl, pt, ro, ru, sk, sl, sr, sv, tr, zh
* Counter: Shariff Backend PHP integration
 
<b>Future plans & Roadmap:</b>
- [x] v3.0 - Initial release with Heise Shariff Library and Shariff Backend integration
- [x] v3.1 - Icon ordering, more bugifixing, facebook api integration, move assets folder to the joomla media folder
- [x] v3.2 - Custom settings for theme and orienation in every shorttag e.g. {shariff orientation:vertical theme:grey}
- [x] v3.2.2 - Reduce the size of the plugin (get rid of the Shariff Backend docs and fluff)
- [x] v3.2.5 - Check the Joomla and PHP Version and prevent the installation if not the requirements met
- [x] v3.2.8 - New: AddThis button
- [x] v3.2.9 - New: tumblr button
- [x] v3.3.0 - Shorttag parameter support for custom icons, backend integration and other shariff settings
- [x] v3.3.0 - Rearrange the Plugin Options and simplify settings
- [ ] Ongoing integration of updates from Shariff Libraray & Backend
<<<<<<< HEAD
- [ ] More Options for Info and Print soon

<h2>Update Instructions:</h2>

* Alle previous versions before 4.0
=======
- [ ] Info Page Urls and Print Service Additions

<h2>Update Instructions:</h2>
* All previous versions before 4.0.0
>>>>>>> master
  * You need to uninstall the old Jooag Shariff Plugin
  * Make a fresh install and do the needed Settings again, please.

<h2>Documentation:</h2>
<h4>First steps</h4>

* Install the latest Plugin Version.
* Login into your Joomla Backend and go to Extensions->Plugins and find the "Jooag Shariff" Plugin.
* Open the Plugin and activate it.
* Goto the "Services" Tab and add your needed services.
* Goto the "Output" Tab and go through the settings.
=======

* Login into your Joomla Backend and go to Extensions->Plugins and find the "Jooag Shariff" Plugin.

* Open the Plugin and activate it.

* Goto the "Services" Tab and add your needed services.

* Goto the "Output" Tab and go through the settings.

>>>>>>> master
* Goto the "Counter" Tab and activate the Counter, if you like to see the count of the shares.

<h4>Share Counter:</h4>
It's really important for the counter to use the url only with www or non-www.
<h6>To redirect www to non-www do the following steps:</h6>
<ol>
<li>Rename the htaccess.txt in your joomla root folder to .htaccess</li>
<li>Add following lines in the end of the .htaccess file</li>
<pre>
# www to non-www
RewriteBase /
RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]
RewriteRule ^(.*)$ http://%1/$1 [R=301,L]
</pre>
or
<pre>
# non-www to www
RewriteCond %{HTTP_HOST} !^www\.
RewriteRule ^(.*)$ http://www.%{HTTP_HOST}/$1 [R=301,L]
</pre>
<li>At least you need to open this plugin and save it again!</li>
</code>
</ol>
</p>

<h4>Use this Plugin as a Shariff Joomla Modul</h4>

* You can put into your content or "Custom Html" module the following shortcode and set the Option "Prepare Content" to yes!

 ```
{shariff}
```

* You can also override the Global Plugin Settings for theme and orientation like this:

```
 {shariff theme=color}
 {shariff theme=grey}
 {shariff theme=white}
 {shariff orientation=horizontal}
 {shariff orientation=vertical}
```

* You can combine this tags, too:

```
 {shariff theme=color orientation=horizontal}
 {shariff theme=white orientation=vertical}
```

* Theme variables
 * color
 * grey
 * white
* Orientation variables
 * horizontal
 * vertical
 
<h2>Credits:</h2>
Developed by https://joomla-agentur.de

Thanks Heise.de for this solution  https://github.com/heiseonline/shariff

Thanks Joomla! our lovely CMS https://joomla.org

and for Joomla! Joomla User Group Hamburg https://jug-hamburg.de/ (the main reason for this plugin :-)

<h2>Many thanks for your help and support!</h2>

* David Jardin - https://www.djumla.de/
* Robert Deutz - https://rdbs.de/
* Tobias Zulauf - https://www.jah-tz.de/
* Viktor Vogel - https://joomla-extensions.kubik-rubik.de/
* Yves Hoppe - https://compojoom.com/
