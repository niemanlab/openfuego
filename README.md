# OpenFuego

OpenFuego is the open-source version of [http://www.niemanlab.org/fuego](Fuego), a heat-seeking Twitter bot originally created to track the future-of-journalism crowd on Twitter.

OpenFuego is essentially “Fuego in a box,” allowing you to monitor your own universe of people on Twitter — celebrities, authors, tech writers, film critics, or just people you like.

Fuego and OpenFuego were created by [https://twitter.com/andrewphelps](Andrew Phelps) for the [http://www.niemanlab.org/](Nieman Journalism Lab).

### How it works

1. __Curate.__ You select up to 15 people — authorities — to form the center of OpenFuego’s universe.

2. __Automate.__ OpenFuego follows those authorities, as well as all of the people they follow, for a total of 5,000 potential sources. Each and every time one of those sources shares a link, OpenFuego captures it into a database with some simple analytics. OpenFuego is running in the background 24 hours a day.

3. __Query.__ You can query OpenFuego to determine which links are being talked most talked about in that universe. OpenFuego does some math to strike a good balance between quality and freshness, then returns a ranked list of URLs and metadata.

### Requirements and notes

OpenFuego is a backend application that runs at the command line. There is nothing to look at! It’s up to you to build a cool webpage or app that does something with the data. To see an example, visit [http://www.niemanlab.org/fuego](Fuego).

OpenFuego is PHP. It requires PHP 5.3.0 or higher and the cURL library. You probably need root access. It probably doesn't work in Windows. OpenFuego is designed to run continuously in background processes, like a daemon. If you know much about programming, you know PHP is a really bad language for this type of program. PHP is what I knew when I first sat down to write the program, and by the time it was big and complex, it would have been too much work to learn a different language and start from scratch.

### Installation

Follow the instructions in config.php. Create a MySQL database and enter the credentials in that file, along with Twitter credentials and optional (but recommended) API keys for Bitly, Goo.gl, and Embed.ly.

Once config.php is edited, run fetch.php at the command line. You may or not get further instructions, depending on whether your version of PHP is compiled with process control.

If you write any scripts that query OpenFuego, include init.php at the top of the file. The application resides in the OpenFuego [http://php.net/manual/en/language.namespaces.php](namespace).

### Usage

See examples/getLinks.php for a dead-simple way to query OpenFuego for links.

### About Nieman Journalism Lab

The [http://www.niemanlab.org/](Nieman Journalism Lab) ([https://twitter.com/niemanlab](@niemanlab)) is an attempt to help journalism figure out its future in an Internet age. It is a project of the Nieman Foundation for Journalism at Harvard University.