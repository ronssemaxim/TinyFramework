<?php
/**
*	Framework.php, extension for TinyFramework; made by Maxim Ronsse (ronsse.maxim@gmail.com) as an extension to debug and trace errors
*	
*/

class TFExtensionDebug { // ArrayAccess to enable the user to do $framework['var']
	public function initialize($framework) {
		$framework->debug = false;

		// set handler for fatal errors
		//register_shutdown_function(array(&$this, "fatalHandler"));
		set_error_handler(array(&$framework, "fatalHandler"));

		// set debugging options, just in case a fatal error occurs
		if($framework->debug) {
			ini_set('error_reporting', -1);
			ini_set('display_errors', 1);
		}
		else {
			ini_set('error_reporting', 0);
			ini_set('display_errors', 0);
		}
	}

	/**
	* handle the exception and take actions
	*/
	public function handleException($framework, $e) {
		if($e == null) {
			$file = 'Unknown';
			$line = 'Unknown';
			$msg = 'Fatal error';
		}
		else {
			$file = $e->getFile();
			$line = $e->getLine();
			$msg = $e->getMessage();
		}


		$logId = $e == null ? 'Unknown' : chr(mt_rand(97, 122)).substr(md5(time()), 1);
		// log to file
		$myFile = __DIR__ . "/../logs/error.txt";
		$fh = fopen($myFile, 'a');
		$stringData = date('d/m/Y H:i:s')."\t".(is_a($e, 'Error') ? 'Fatal ' : '')."Error thrown in ".$file." on line ".$line.": ".$msg.". Log id: $logId\n";
		fwrite($fh, $stringData);
		fclose($fh);
		if($e != null) {			
			// details
			$myFile = __DIR__ . "/../logs/errorDetails/$logId.txt";
			$fh = fopen($myFile, 'a');
			$stringData = "Date: ".date('d/m/Y H:i:s')."\n";
			$stringData .= (is_a($e, 'Error') ? 'Fatal ' : '')."Error thrown in ".$file." on line ".$line.": ".$msg."\n";
			$stringData .= "Log id: $logId\n\n";

ob_start();
var_dump($e->getTrace());
$result = ob_get_clean();
			$stringData .= "Trace: \n".$result;
			fwrite($fh, $stringData);
			fclose($fh);
		}

		// display log, rethrow, or show user error page
		if($framework->debug) {
			$this->printDebugTrace($e);
		}
		else
		if($rethrowException == true) { // rethrow exception if desired
			throw $e;
		}
		else { // default error page
			?><!DOCTYPE html>
			<html>
			<head>
				<title>Error 500</title>
				<meta charset="utf8" />
				<style>
					body {
						background-color: #111;
						background-repeat: no-repeat, repeat;
						background-position: 0 0;
						background-size: 100% auto, 400px 400px;
						padding: 20px;
						font-family: "URW Palladio L",Palatino,"Book Antiqua","Palatino Linotype",serif;
						color: rgb(232,220,188);
						font-size: 13px;
						letter-spacing: 1px;
						max-width: 1024px;
						margin: 0 auto;
					}
				</style>
			</head>

			<body>
				<h1>Error 500</h1>
				An error occured while trying to process the page you requested!
			</body>

			</html>
			<?php
		}
	}

	// Print a user friendly error page, using the given $e exception
	private function printDebugTrace($e) {
		?><!DOCTYPE html>
		<html>
		<head>
			<title>Error debugging</title>
			<meta charset="utf8" />
			<style>
				body {
					font-family: Arial;
				}
				a {
					background: #0AD;
					color: white;
					display: inline-block;
					text-decoration: none;
					border-radius: 4px;
					padding: 5px 15px;
					float: right;
					transition: all .4s;
					-webkit-transition: all .4s;
				}
				a:hover {
					background: #08C;
				}
				a:active {
					box-shadow: 0 0 10px rgba(0, 0, 0, .4) inset;
				}
				.args {
					display: none;
				}
				.args div {
					padding-left: 20px;
					border-left: 1px dotted #CCC;
					display: none;
				}

				.args p {
					border-radius: 4px;
					background: #FFF;
					transition: all .2s;
					-webkit-transition: all .2s;
				}
				.args p.red {
					background: #FF5878
				}
				.args p.green {
					background: #58FF78
				}
				.args p.clickable {
					cursor: pointer;
					text-decoration: underline;
				}

				div.trace {
					margin-left: 20px;
					border-bottom: 1px solid #CCC;
				}

				div.code span.active {
					background: #C6C6C6;
					display: block;
					border-radius: 4px;
					color: #000;
				}
				div.code span.active.red {
					background: #FF5878;
				}

				span.fatal {
					background: #F00;
					color: white;
					text-shadow: 0 0 4px white;
					border-radius: 4px;
					padding: 3px 8px;
					animation: redBlink .8s linear infinite;
				}

				@keyframes redBlink {
					0% {
						background-color: red;
					}
					49% {
						background-color: red;
					}
					50% {
						background-color: white;
					}
					100% {
						background-color: white;
					}
				}
			</style>
		</head>
		<body>
			<h1>Error debugging</h1>
			<?php
			if($e == null) {
				echo 'Fatal unkown error, no details available :/';
			}
			else {
				echo (is_a($e, 'Error') ? '<span class="fatal">Fatal</span> ' : '').'Error thrown in '.$e->getFile().' on line '.$e->getLine().': '.$e->getMessage();


				// if it's a custom error, remove first trace item, because it is the location where the error was instantiated
				$trace = $e->getTrace();
				if(is_a($e, 'Error')) {		
					array_shift($trace);
				}
				foreach ($trace as $key => $value) {
					echo '<h2>'.(isset($value['class']) ? $value['class'] : $value['file'] ).($key == 0 ? ' (origin)' : '').'</h2><div class="trace">';
					if(isset($value['line']))
						echo '<p>Called on line '.$value['line'].'</p>';
					else
					if($key == 0) {
						echo '<p>Called on line '.$e->getLine().'</p>';
						$value['file'] = $e->getFile();
						$value['line'] = $e->getLine();
					}

					if(isset($value['file']) || $key == 0) {

						$contents = file_get_contents($value['file']);
						$lines = explode("\n", $contents);
						echo '<div class="code">';
						for($i = max(0, $value['line']-6); $i < $value['line']+5 && $i < count($lines); $i++) {
							if($i == $value['line']-1)
								echo '<span class="active'.($key == 0 ? ' red' : '').'">';
							echo str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $lines[$i]).'<br />';
							if($i == $value['line']-1)
								echo '</span>';
						}
						echo '</div>';
					}

					if(isset($value['function'])) {
						echo '<a title="Click to view arguments" href="#">Arguments ('.count($value['args']).')</a>';
						echo '<p>In function '.$value['function'].'</p>';
						echo '<div class="args">';
						var_dump($value['args']);
						echo '</div>';
					}
					echo '</div>';
				}
				?>
				<script src="//code.jquery.com/jquery-1.11.3.min.js"></script>
				<script type="text/javascript">
					$(document).ready(function() {
						$('a').click(function(e) {
							e.preventDefault();
							var args = $(' .args', $(this).parent());

							var lines = $(args).html().split(/\n/);
							var spacesLeft = 0;

							var txt = "";

							var isClickable = function(line) {
								return (line.indexOf('array(') === 0 ||  line.indexOf('object(') === 0 ? ' class="clickable" title="Click to expand"' : '');
							}
							for(var i = 0; i < lines.length; i++) {
								j = 0;
								while(lines[i][j] == ' ')
									j++;


								lines[i] = lines[i].trim();
								if(j > spacesLeft) {
									lines[i] = '<div><p' + isClickable(lines[i]) + '>' + lines[i] + '</p>';
								}
								else
								if(j < spacesLeft) {
									lines[i] = '</div><p' + isClickable(lines[i]) + '>' + lines[i] + '</p>';
								}
								else {
									lines[i] = '<p' + isClickable(lines[i]) + '>' + lines[i] + '</p>';
								}

								spacesLeft = j;
							}
							$(args).html(lines.join(''));

							args.slideToggle();
						});
						$(document).on('click', 'div.args p', function() {
							var t = this;
							var div = $(this).next('div');
							if(div.length < 1) {
								$(t).addClass('red');
								setTimeout(function() {
									$(t).removeClass('red');
								}, 200);
							}
							else {
								$(t).addClass('green');
								setTimeout(function() {
									$(t).removeClass('green');
								}, 200);
								div.slideToggle();
							}
						})
					});
				</script>
			</body>
			</html>
			<?php
			}
		exit(0);
	}

	/**
	* get's called when a fatal exception occures
	*/
	public function fatalHandler($framework, $errno, $errstr, $errfile, $errline) {
		$e = new Error($errstr, $errno, $errfile, $errline = $errline);
		TinyFramework::handleException($e);
		return true;
	}
}




// custom error class to initialize an Exception with custom attributes 
class Error extends Exception{
	public function __construct($message, $code, $file, $line) {
		$this->message = $message;
		$this->code = $code;
		$this->file = $file;
		$this->line = $line;
	}

	public function setTrace($trace) {
		$this->trace = $trace;
	}
}