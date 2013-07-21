<?php namespace OpenFuego\lib;

class Logger {
	
	public static $verbose = \OpenFuego\VERBOSE;
	public static $tmp;
	
	public static function debug($message) {
		
		if (self::$verbose) {
			echo self::getTimestamp() . $message . "\n";
		}
	}
	
	public static function info($message) {
		if (self::$verbose) {
			echo self::getTimestamp() . $message . "\n";
		}
		
		// write to log
	}

	public static function error($message) {
		if (self::$verbose) {
			echo self::getTimestamp() . $message . "\n";
		}
		
		// write to log
	}
	
	public static function fatal($message) {
		if (self::$verbose) {
			echo self::getTimestamp() . $message . "\n";
		}

		else {
			self::notify($message);	
		}

		// write to log
	}
	
	private static function notify($message) {
		mail(\OpenFuego\WEBMASTER, 'OpenFuego encountered a fatal error', $message, 'From: ' . \OpenFuego\POSTMASTER);
	}
	
	private static function getTimestamp() {
		return '[' . date('Y-m-d H:i:s') . ']: ';
	}
}