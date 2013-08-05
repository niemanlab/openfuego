# OpenFuego

OpenFuego is the open-source version of [Fuego](http://www.niemanlab.org/fuego), a Twitter bot created to track the future-of-journalism crowd and the links they’re sharing.

OpenFuego is essentially “Fuego in a box,” allowing you to monitor your own universe of people on Twitter — celebrities, tech writers, film critics, Canadians, or just people you like.

Fuego and OpenFuego were created by [Andrew Phelps](https://twitter.com/andrewphelps) for the [Nieman Journalism Lab](http://www.niemanlab.org/). It was influenced by [Hourly Press](http://www.theatlantic.com/technology/archive/2011/08/fuego-a-bot-that-turns-the-twitter-firehose-into-a-trickle/244355/), created by Steve Farrell and Lyn Headley. OpenFuego relies heavily on @fennb’s [Phirehose](https://github.com/fennb/phirehose) library and @abraham’s [twitteroauth](https://github.com/abraham/twitteroauth) library.

### How it works

1. __Curate.__ You select up to 15 Twitter users — authorities — to form the center of OpenFuego’s universe.

2. __Automate.__ OpenFuego follows those authorities, as well as all of the people they follow, up to a total of 5,000 sources. Each and every time one of those sources shares a link, OpenFuego captures it into a database with some simple analytics. OpenFuego is running in the background 24 hours a day.

3. __Query.__ You can query OpenFuego to determine which links are being talked about most in that universe. OpenFuego does some math to strike a good balance between quality and freshness, then returns a ranked list of URLs and metadata.

### Installation

OpenFuego is a backend application that runs at the command line. There is nothing to look at! It’s up to you to build a cool webpage or app that does something with the data. To see a real example, visit [Fuego](http://www.niemanlab.org/fuego).

Follow the instructions in config.php. Create a MySQL database and enter the credentials in that file, along with Twitter credentials and optional (but recommended) API keys for Bitly, Goo.gl, and Embed.ly.

### Usage

Once config.php is edited, run `fetch.php` at the command line. You may or not get further instructions, depending on whether your version of PHP is compiled with process control.

__Recommended option for new users:__ To run OpenFuego in verbose mode, which displays helpful errors and warnings on screen, run `fetch.php -v`.

You can `kill` the two processes at any time. The script may take a few seconds to clean up before terminating. Always make sure to kill any and all old OpenFuego processes before initializing.

Include init.php at the top of any script that queries OpenFuego. See examples/getLinks.php for a dead-simple example and more instructions.

### Requirements and notes

OpenFuego is PHP. It requires PHP 5.3.0 or higher, MySQL 5.0 or higher, and a *nix environment. In many cases the program won’t work in shared hosting environments and you’ll need root access. This is because OpenFuego is designed to run continuously in the background, like a daemon. (If you know much about programming, you know PHP is a bad language for this type of program. PHP is what I knew when I first sat down to write the program, and by the time it became big and complex, it would have been too much work to learn a different language and start from scratch.)

OpenFuego is really three discrete programs: the Collector, the Consumer, and the Getter. The Collector is constantly connected to [Twitter’s streaming API](https://dev.twitter.com/docs/streaming-apis), saving all new tweets to temporary files on disk. The Consumer is constantly parsing those files, extracting the URLs, cleaning them up, and saving them to a database. The Collector and the Consumer run concurrently in separate processes. The Getter is a class that retrieves URLs from the database and does some math to tell you what’s most popular in a given timeframe. If you specify an Embed.ly API key, the Getter can optionally return fully hydrated metadata for the URLs.

---

### About Nieman Journalism Lab

The [Nieman Journalism Lab](http://www.niemanlab.org/) ([@niemanlab](https://twitter.com/niemanlab)) is a collaborative attempt to figure out how quality journalism can survive and thrive in the Internet age. It’s a project of the [Nieman Foundation for Journalism](http://www.nieman.harvard.edu) at Harvard University.
