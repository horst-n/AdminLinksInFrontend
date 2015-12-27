<?php
/*******************************************************************************
  *  @script_type -   PHP-CLASS
  *  @php_version -   4.2.x
  *  @SOURCE-ID   -   1.205
  * ---------------------------------------------------------------------------
  *  @version     -   v3.5
  *  @date        -   $Date: 2014/04/16 08:04:08 $
  *  @author      -   Horst Nogajski <coding AT nogajski DOT de>
  *  @copyright   -   (c) 1999 - 2013
  *  @licence     -   LGPL
  * ---------------------------------------------------------------------------
  *  $Source: /PHP-CLI/php_includes/hn_basic.class.php,v $
  *  $Id: hn_basic.class.php,v 1.211 2014/04/16 08:04:08 horst Exp $
  ******************************************************************************
  *
  * LAST CHANGES:
  *
  * 2010-09-12   new     class       hn_path_object()
  *              new     hn_basic -> hn_BuildDirStr( $dir_in, $part='fullname' )
  *              new     hn_basic -> hn_BuildFileStr( $file_in, $part='fullname' )
  *
  * 2011-10-13   new     hn_basic -> get_memory_limit( $userfriendly=FALSE )
  *              new     hn_basic -> get_memory_usage( $userfriendly=FALSE )
  *              new     hn_basic -> get_memory_available( $userfriendly=FALSE )
  *
  * 2012-01-12   fix     hn_basic -> date_ftpraw2timestamp( $s )       bei der Umwandlung von split nach explode vergessen das Plus rauszunehmen (split arbeitet mit RegExp, explode mit String): ' +' => ' '
  *              fix     hn_basic -> date_ftpraw2timestamp( $s )       Beruecksichtigung von Schaltjahren bei der Berechnung
  *
  * 2012-01-15   change  hn_basic -> string2file()                     es wird file_put_contents() genutzt falls es existiert
  *
  * 2012-02-02   fix     hn_basic -> date_ftpraw2timestamp( $s )       entfernen von doppelten oder mehrfachen Leerzeichen in $s bevor der String mit explode zerlegt wird!
  *
  * 2012-02-09   new     constant    FILE_APPEND for PHP-Version 4.x   WICHTIG fuer file_put_contents()!!
  *
  * 2012-11-22   new     function    hn_studiologger( $logcategory, $s1='', $s2='', $s3='', $s4='', $s5='' )
  *
  * 2012-11-26   new     class       xip
  *              new     function    hn_get_xip()
  *
  * 2012-12-23   new     hn_basic -> sort_IPv4( $array_with_ips, $descending=FALSE )
  *
  * 2012-12-27   fix     hn_basic -> txt_counter() nutzt jetzt file-locking!
  *
  * 2013-01-25   change  hn_basic    now extends class hn_php4_destructor
  *              change  class       filo                moved into file 'hn_basic_helpers.class.php'
  *              change  class       timer               moved into file 'hn_basic_helpers.class.php'
  *              change  class       hn_php_api          moved into file 'hn_basic_helpers.class.php'
  *              change  class       hn_SizeCalc         moved into file 'hn_basic_helpers.class.php'
  *              change  class       hn_memory_usage     moved into file 'hn_basic_helpers.class.php'
  *              change  class       hn_php4_destructor  moved into file 'hn_basic_helpers.class.php'
  *
  * 2013-02-02   new     hn_basic -> new_password( $length=8, $aChars=array() )
  *
  * 2013-02-12   new     hn_basic -> cl_name_from_argv( $index=1, $dirname=false, $die_on_failure=false )
  *
  * 2013-02-12   change  hn_basic -> dl( $extension, $alternate=null )  = added second param '$alternate' to check e.g for GD2 and GD at once
  *
  * 2014-04-16   change  hn_basic -> my_var_dump()  = changed font-family for BrowserOutput
  *
**/




require_once( dirname(__FILE__).'/hn_basic_helpers.class.php' );

	if(!function_exists('hn_dl'))
	{
		function hn_dl($extension)
		{
			$extension = strtolower(trim($extension));
			if(extension_loaded($extension))
			{
				return true;
			}
			$is_enabled = intval(ini_get('enable_dl'))==1 && intval(ini_get('safe_mode'))!=1 ? TRUE : FALSE;
			if(!$is_enabled)
			{
				return false;
			}
			$dll = preg_match('/^Windows/i',php_uname()) ? 'php_'.$extension.'.dll' : $extension.'.so';
			$res = intval(@dl($dll));
			return extension_loaded($extension);
		}
	}



	// DEBUG-/LOGTYPE Definitions
	if(!defined('DBG_LOG_NONE')) define( 'DBG_LOG_NONE', 0 );	// No log-operations
	if(!defined('DBG_LOG_HTML')) define( 'DBG_LOG_HTML' ,1 );	// new Constant for output to screen for Webbrowsers
	if(!defined('DBG_LOG_CMDL')) define( 'DBG_LOG_CMDL', 2 );	// output to screen for CommandlineConsole
	if(!defined('DBG_LOG_HIDE')) define( 'DBG_LOG_HIDE', 3 );	// output to HTML-comment <!-- -->
	if(!defined('DBG_LOG_FILE')) define( 'DBG_LOG_FILE', 4 );	// Logentry into file
																// (you can also use combinations with FILE:
																//  HTML + FILE | CMDL + FILE | HIDE + FILE)

	if(!defined('DBG_LOG_ECHO')) define( 'DBG_LOG_ECHO', 1 );	// old Constant for output to screen for Webbrowsers



/** This class holds some basic methods which are often required by any other
  * of my classes, which therefore always extend the hn_basic class.
  *
  * These are mainly small help functions like:
  *
  *    setCacheArray | getCacheArray
  *
  *    string2file | file2string | array2file | file2array | etc.
  *
  *    sorting methods for multidimensional-Arrays (hn_array_sort | hn_arrayKey_sort)
  *
  * or e.g.:
  *
  *    the debug and logging methods
  *
  *    the destructor-method (what is not implemented natively in PHP < 5)
  *
  *    the ScriptTimeout-method, which for example can be used
  *    in Webenvironments (also running in PHP-Safemode) to make possible an endless
  *    execution time, or, the opposite, to make possible a ScriptTimeout for CommandlineScripts with the PHP-CLI
  *
  *    the txt_counter method (and it's selftest method)
  *
  * etc. etc. (have a look to the code)
  *
  **/
if(!class_exists('hn_basic')) {

class hn_basic extends hn_php4_destructor
{

	/////////////////////////////////////////////
	//	PUBLIC PARAMS


		/** Defines the type of debugging / logging.
          * You can use this constants:
          * [ DBG_LOG_NONE | DBG_LOG_HTML | DBG_LOG_CMDL | DBG_LOG_HIDE | DBG_LOG_FILE ]
          * or this combinations:
          * [ DBG_LOG_HTML + DBG_LOG_FILE  | DBG_LOG_CMDL + DBG_LOG_FILE  | DBG_LOG_HIDE + DBG_LOG_FILE ]
          *
		  * @shortdesc defines the type of logging / debugging
		  * @public
		  * @type integer
		  **/
		var $debugoutput 	= 0; // DBG_LOG_NONE


		/** A call to the debug-func (optionally) can have an ID-string.
          * This will checked against this pattern. If it matches, the
          * logging/debugging is allowed, otherwise will be ignored.
          * This allows to define lots of debug-calls in script's
          * (and classes) and having a good method to filter.
          *
		  * @shortdesc a RegEx-pattern used to select debug-output
		  * @public
		  * @type string
		  **/
		var $debugpattern 	= '/.*/';


		/** @shortdesc Contains the message for the Debug-/Loggingfunc
		  * @public
		  * @type string
		  **/
		var $msg 			= '';


		/** @shortdesc Prefix for Logfilename
		  * @public
		  * @type string
		  **/
		var $logfile 		= "hn_classes_log";


		/** @shortdesc The String which should be used as separator for entries in Logfile.
		  * @public
		  * @type string
		  **/
		var $lf_separator 	= "\n";



		var $MIMETYPES = array(
			'xls'		 => 	'application/excel',
			'hlp'		 => 	'application/hlp',
			'ppt'		 => 	'application/mspowerpoint',
			'doc'		 => 	'application/msword',
			'dot'		 => 	'application/msword',
			'pdf'		 => 	'application/pdf',
			'ai'		 => 	'application/postscript',
			'eps'		 => 	'application/postscript',
			'ps'		 => 	'application/postscript',
			'bin'		 => 	'application/x-binary',
			'jsx'		 => 	'application/x-extended-javascript',
			'gz'		 => 	'application/x-gzip',
			'js'		 => 	'application/x-javascript',
			'swf'		 => 	'application/x-shockwave-flash',
			'tar'		 => 	'application/x-tar',
			'xml'		 => 	'application/xml',
			'zip'		 => 	'application/zip',
			'mpga'		 => 	'audio/mpeg',
			'mp3'		 => 	'audio/mpeg3',
			'wav'		 => 	'audio/wav',
			'm3u'		 => 	'audio/x-mpequrl',
			'bmp'		 => 	'image/bmp',
			'g3'		 => 	'image/g3fax',
			'gif'		 => 	'image/gif',
			'jpeg'		 => 	'image/jpeg',
			'jpg'		 => 	'image/jpeg',
			'png'		 => 	'image/png',
			'tif'		 => 	'image/tiff',
			'tiff'		 => 	'image/tiff',
			'ico'		 => 	'image/x-icon',
			'pnm'		 => 	'image/x-portable-anymap',
			'pbm'		 => 	'image/x-portable-bitmap',
			'pgm'		 => 	'image/x-portable-graymap',
			'ppm'		 => 	'image/x-portable-pixmap',
			'css'		 => 	'text/css',
			'htm'		 => 	'text/html',
			'html'		 => 	'text/html',
			'cache'		 => 	'text/plain',
			'conf'		 => 	'text/plain',
			'log'		 => 	'text/plain',
			'txt'		 => 	'text/plain',
			'rtf'		 => 	'text/rtf',
			'py'		 => 	'text/x-script.phyton',
			'avi'		 => 	'video/avi',
			'avs'		 => 	'video/avs-video',
			'mp2'		 => 	'video/mpeg',
			'mpe'		 => 	'video/mpeg',
			'mpeg'		 => 	'video/mpeg',
			'mpg'		 => 	'video/mpeg'
		);



		var $long_lat_positions = array(
			'aachen'		=> '50.775466:6.081478',
			'berlin'		=> '52.523405:13.4114',
			'muenchen'		=> '48.139127:11.580186',
			'stockholm'		=> '59.332788:18.064488',
			'london'		=> '51.500152:-0.126236',
			'glasgow'		=> '54.964486:-1.469518',
			'paris'			=> '48.856667:2.350987',
			'rom'			=> '41.895466:12.482324',
			'barcelona'		=> '41.387917:2.169919',
			'madrid'		=> '40.416691:-3.700345',
			'lisabon'		=> '38.707054:-9.135488',
			'ankara'		=> '39.92077:32.85411',
			'istanbul'		=> '41.00527:28.97696',
			'kathmandu'		=> '27.702871:85.318244',
			'shanghai'		=> '31.230708:121.472916',
			'peking'		=> '39.904667:116.408198',
			'washington'	=> '38.895112:-77.036366',
			'new york'		=> '40.714269:-74.005973',
			'peacehaven'	=> '50.79000:0',
			'porto alegre'	=> '0.035:6.535',
			'moskau'		=> '55.755786:37.617633'
		);


	/////////////////////////////////////////////
	//	PRIVATE PARAMS

		/** @shortdesc Filehandle to Logfile
		  * @private
		  **/
		var $logfilehdl = null;


		/** @shortdesc stores the value of default error reporting
		  * @private
		  **/
		var $default_error = array();



	/////////////////////////////////////////////
	//	CONSTRUCTOR

		/** Constructor
		  * @public
		  **/
		function hn_basic($config='',$secure=TRUE)
		{
			$this->getErrorLevel();

			// extracts config Array
			if(is_array($config))
			{
				if($secure && strcmp('4.2.0', phpversion()) < 0)
				{
					$valid = get_class_vars(get_class($this));
					$extracted = 0;
					$skipped = array();
					foreach($config as $k=>$v)
					{
						if(array_key_exists($k,$valid))
						{
							$this->$k = $v;
							$extracted++;
							$extracted_temp[$k] = $v;
						}
						else
						{
							$skipped[$k] = $v;
						}
					}
					$this->msg = 'Config-Array extracted in secure-mode: $hn_basic->CONSTRUCTOR() [ possible: '.count($valid).' | extracted: '.$extracted.' | skipped: '.(count($config)-$extracted).' ]';
					$this->debug('hn_basic,ini');
					if(count($skipped)>0)
					{
						$display_mode = $this->debugoutput < 4 ? $this->debugoutput : $this->debugoutput -4;
						if($display_mode>0)
						{
							$this->my_var_dump(array('skipped-vars'=>$skipped,'extracted-vars'=>$extracted_temp),$display_mode);
						}
					}
					if(isset($extracted_temp)) unset($extracted_temp);
				}
				else
				{
					foreach($config as $k=>$v) $this->$k = $v;
					$this->msg = 'Config-Array extracted in _UN_secure-mode: $hn_basic->CONSTRUCTOR() [ extracted: '.count($config).' ]';
					$this->debug('hn_basic,ini');
				}
			}
			else
			{
				$this->msg = 'Initialized without Config-Array: $hn_basic->CONSTRUCTOR()';
				$this->debug('hn_basic,ini');
			}
		}


	/////////////////////////////////////////////
	//	DESTRUCTOR

		/** @shortdesc This add a func-call to Globalarray which will executed when Destructor runs!
		  * @public
		  **/
		function runOnShutdown( $func='' )
		{
			// first call of function
			if(!isset($GLOBALS['__hn_ShutdownFunction__']))
			{
				if($this->msg == '')
				{
					$this->msg = '- Initiate hn_basic_Destructor as main register_shutdown_function!';
					$this->debug('ini,hn_basic,close,shutdown,sys');
				}
				else
				{
					$this->msg = '- Initiate hn_basic_Destructor as main register_shutdown_function!'.$this->lf_separator.$this->msg;
				}
				$this->hn_php4_destructor();
			}
			if($func!='')
			{
				$GLOBALS['__hn_ShutdownFunction__']['__THINX2UNSET__'][md5($func)] = addslashes($func);
				if($this->msg == '')
				{
					$this->msg = "- register a ShutDownFunc: $func";
					$this->debug('ini,hn_basic,close,shutdown,sys');
				}
				else
				{
					$this->msg = $this->msg.$this->lf_separator."- register a ShutDownFunc: $func";
				}
			}
		}


		/** @shortdesc This will be registered as ShutdownFunc. It closes the Logfilehandle, and execute all code which was added by the runOnShutdown()-func!
		  * @private
		  **/
		function _hn_basic_Destructor()
		{
			# should run only once!
			if( ! isset($GLOBALS['__hn_ShutdownFunction__']['enabled']) )
			{
				return;
			}
			if( $GLOBALS['__hn_ShutdownFunction__']['enabled']!==true )
			{
				$this->msg .= $this->lf_separator.'Running shutdown_function was set to FALSE after it was registered!';
				$this->debug('ini,hn_basic,close,shutdown,sys');
				$this->logfile_close();
				unset($GLOBALS['__hn_ShutdownFunction__']['enabled']);
				return;
			}
			unset($GLOBALS['__hn_ShutdownFunction__']['enabled']);

			$this->msg .= $this->msg == '' ? 'Running shutdown_function: $hn_basic->hn_basic_Destructor()' : $this->lf_separator.'Running shutdown_function: $hn_basic->hn_basic_Destructor()';
			$this->debug('ini,hn_basic,close,shutdown,sys');
			if( isset($GLOBALS['__hn_ShutdownFunction__']['__THINX2UNSET__']) && is_array($GLOBALS['__hn_ShutdownFunction__']['__THINX2UNSET__']) )
			{
				foreach( $GLOBALS['__hn_ShutdownFunction__']['__THINX2UNSET__'] as $func )
				{
					$func = stripslashes($func);
					$this->msg = "- running: $func";
					$this->debug('ini,hn_basic,close,shutdown,sys');
					eval("$func");
				}
			}
			$this->logfile_close();
		}


	/////////////////////////////////////////////
	//  PUBLIC METHODS




		# Extension Loader
		function dl( $extension, $alternate=null )
		{
			$extension = strtolower(trim( $extension ));
			if( extension_loaded($extension) )
			{
				return true;
			}
			if( is_string($alternate) )   // a workaround for e.g.: GD2 can have name GD or GD2
			{
				$alternate = strtolower($alternate);
				if( extension_loaded($alternate) )
				{
					return true;
				}
			}
			$is_enabled = intval(ini_get('enable_dl'))==1 && intval(ini_get('safe_mode'))!=1 ? true : false;
			if( ! $is_enabled )
			{
				return false;
			}
			$dll = preg_match("/^Windows/i",php_uname()) ? 'php_'.$extension.'.dll' : $extension.'.so';
			$res = intval(@dl($dll))===1 ? true : false;
			if( $res )
			{
				return true;
			}
			if( is_string($alternate) )
			{
				$dll = preg_match("/^Windows/i",php_uname()) ? 'php_'.$alternate.'.dll' : $alternate.'.so';
				$res = intval(@dl($dll))===1 ? true : false;
			}
			return $res;
		}






		function InputSecurityCheck($exclude_GET=FALSE,$exclude_POST=FALSE,$exclude_COOKIE=FALSE,$exclude_REQUEST=FALSE)
		{
			if(!$exclude_GET)
			{
				if(is_array($_GET))
				{
					while(list($key, $value) = each($_GET))
						$_GET["$key"] = strip_tags($value);
				}
			}
			if(!$exclude_POST)
			{
				if(is_array($_POST))
				{
					while(list($key, $value) = each($_POST))
						$_POST["$key"] = strip_tags($value);
				}
			}
			if(!$exclude_COOKIE)
			{
				if(is_array($_COOKIE))
				{
					while(list($key, $value) = each($_COOKIE))
						$_COOKIE["$key"] = strip_tags($value);
				}
			}
			if(!$exclude_REQUEST)
			{
				if(isset($_REQUEST) && is_array($_REQUEST))
				{
					while(list($key, $value) = each($_REQUEST))
						$_REQUEST["$key"] = strip_tags($value);
				}
			}
		}



		function getErrorLevel()
		{
			$this->default_error['level']   = error_reporting();
			$this->default_error['display'] = ini_get('display_errors');
			$this->default_error['html']    = ini_get('html_errors');
		}

		function setErrorLevel($level=FALSE,$display=NULL,$html=NULL)
		{
			/*
			    Value	Constant (integer)	Description
				1		E_ERROR 			Fatal run-time errors. These indicate errors that can not be recovered from, such as a memory allocation problem. Execution of the script is halted.
				2		E_WARNING 			Run-time warnings (non-fatal errors). Execution of the script is not halted.
				4		E_PARSE 			Compile-time parse errors. Parse errors should only be generated by the parser.
				8		E_NOTICE 			Run-time notices. Indicate that the script encountered something that could indicate an error, but could also happen in the normal course of running a script.
				16		E_CORE_ERROR 		Fatal errors that occur during PHP's initial startup. This is like an E_ERROR, except it is generated by the core of PHP.
				32		E_CORE_WARNING 		Warnings (non-fatal errors) that occur during PHP's initial startup. This is like an E_WARNING, except it is generated by the core of PHP.
				64		E_COMPILE_ERROR 	Fatal compile-time errors. This is like an E_ERROR, except it is generated by the Zend Scripting Engine.
				128		E_COMPILE_WARNING 	Compile-time warnings (non-fatal errors). This is like an E_WARNING, except it is generated by the Zend Scripting Engine.
				256		E_USER_ERROR 		User-generated error message. This is like an E_ERROR, except it is generated in PHP code by using the PHP func trigger_error().
				512		E_USER_WARNING 		User-generated warning message. This is like an E_WARNING, except it is generated in PHP code by using the PHP func trigger_error().
				1024	E_USER_NOTICE 		User-generated notice message. This is like an E_NOTICE, except it is generated in PHP code by using the PHP func trigger_error().
				2047	E_ALL 				All errors and warnings, as supported, except of level E_STRICT.
				2048	E_STRICT 			Run-time notices. Enable to have PHP suggest changes to your code which will ensure the best interoperability and forward compatibility of your code.
			*/

			if($level !== FALSE) error_reporting($level);
			else error_reporting($this->default_errorlevel);

			if($display !== NULL) ini_set('display_errors', (bool)$display);
			else ini_set('display_errors', (bool)$this->default_error['display']);

			if($html !== NULL) ini_set('html_errors', (bool)$html);
			else ini_set('html_errors', (bool)$this->default_error['html']);
		}



	/////////////////////////////////////////////
	//	DEBUGGING + LOGGING

		/**
		  * This raise a Debug- or Log-output.
		  * The type, and cases of output is dependend on following settings:
          * 1) $classhandle->debugoutput
          * 2) $classhandle->debugpattern
          * 3) $classhandle->logfile
          * 4) $classhandle->msg
          * Typically it is used like this:
          * $classhandle->msg = "This is my message";
          * $classhandle->debug('ID-string');
		  *
		  * @shortdesc Outputs the string currently stored in $classhandle->msg in the way specified with $classhandle->debugoutput and $classhandle->debugpattern
		  * @public
		  *
		  **/
		function debug($match="allesODERnix")
		{
			if(!isset($this->debugpattern) || $this->debugpattern==='') $this->debugpattern = "/.*/";
			if(preg_match($this->debugpattern, $match))
			{
				if($this->debugoutput > 3 && (!$this->logfilehdl)) $this->logfile_init($match);
				switch($this->debugoutput)
				{
					case 0:
						// NO LOG OPERATIONS
						break;
					case 1:
						// SCREEN OUTPUT for Browser
						echo "\n<br>".$this->nlreplace(htmlentities($this->msg),'<br>')."\n";
						break;
					case 2:
						// SCREEN OUTPUT for CommandlineWindow
						echo "\n".$this->msg;
						break;
					case 3:
						// BROWSER SILENT OUTPUT (<!-- -->)
						echo "\n<!-- DEBUG: " . $this->msg . "-->\n";
						break;
					case 4:
						// FILE OUTPUT
						fwrite($this->logfilehdl,$this->lf_separator.$this->msg);
						break;
					case 5:
						// FILE + SCREEN OUTPUT for Browser
						fwrite($this->logfilehdl,$this->lf_separator.$this->msg);
						echo "\n<br>".$this->nlreplace(htmlentities($this->msg),'<br>')."\n";
						break;
					case 6:
						// FILE + SCREEN OUTPUT for CommandlineWindow
						fwrite($this->logfilehdl,$this->lf_separator.$this->msg);
						echo "\n".$this->msg;
						break;
					case 7:
						// FILE + BROWSER SILENT OUTPUT (<!-- -->)
						fwrite($this->logfilehdl,$this->lf_separator.$this->msg);
						echo "\n<!-- DEBUG: " . $this->msg . "-->\n";
						break;
				}
				if(preg_match('/[1-3|5-7]/',$this->debugoutput))
				{
					flush();
				}
				$this->msg = '';
			}
		}


		/** Wrapper for the Debug-function
		  * Use it (for errors only) like this: $this->Error('my message',__FILE__,__FUNCTION__,__LINE__[,TRUE]);
          *
		  * @shortdesc Wrapper for the debug function.
		  * @public
		  **/
		function ErrorMsg($msg,$FILE='',$FUNCTION='',$LINE='',$resetGlobalErrMsg=FALSE)
		{
			$this->msg = "ERROR: [$msg] [$FILE] [$FUNCTION] [$LINE]";
			$this->msg .= isset($php_errormsg) ? " [$php_errormsg]" : '';
			$this->debug('error,Error,ERROR');
			if($resetGlobalErrMsg) $php_errormsg = '';
		}

		/** @shortdesc temporary suppress logging
		  * @public
		  **/
		function quiet($mode=TRUE)
		{
			if($mode)
			{
				$this->dbg = $this->debugoutput;
				$this->debugoutput = 0;
			}
			else
			{
				if(isset($this->dbg)) $this->debugoutput = $this->dbg;
			}
		}

		/** @shortdesc logfile initiation
		  * @private
		  **/
		function logfile_init($match)
		{
			if($this->logfilehdl) return;
			switch($this->debugoutput)
			{
				case 0:			// NO LOG OPERATIONS
				case 1:			// only SCREEN OUTPUT TO BROWSER
				case 2:			// only SCREEN OUTPUT TO CONSOLEWINDOW
				case 3:			// only SILENT OUTPUT (<!-- -->) TO BROWSER
				break;
				case 4: 		// only FILE OUTPUT
				case 5: 		// FILE OUTPUT + SCREEN OUTPUT TO BROWSER
				case 6: 		// FILE OUTPUT + SCREEN OUTPUT TO CONSOLEWINDOW
				case 7: 		// FILE OUTPUT + SILENT OUTPUT (<!-- -->) TO BROWSER
				$d = getdate(gmmktime());
				$this->msg = $this->lf_separator."### ".date("d.m.Y - H:i:s")." START LOGGING ###".$this->lf_separator.$this->msg;
				$this->logfile = $this->logfile . "_" . $d["mon"] . "-" . $d["year"] . ".txt";
				$this->logfilehdl = @fopen($this->logfile,'a+');
				if($this->logfilehdl===FALSE)
				{
					// Fallback to Debug-Output-Mode without Filewriting
					$this->debugoutput -= 4;
					$argh = "ERROR! UNABLE TO OPEN SPECIFIED LOG FILE ".basename($this->logfile).$this->lf_separator."- Fallback to Debug-Output-Mode without Filewriting (".(int)$this->debugoutput.")";
					if($this->debugoutput == 0)
						echo "<!-- $argh -->";
					else
						$this->msg .= $this->lf_separator.$argh;
				}
				else
				{
					$this->runOnShutdown();
					$this->dbgtimer = new timer();
					$this->dbgtimer->timer_start();
					$this->msg .= $this->lf_separator.'- start timer';
				}
				break;
			}
		}


		/** @shortdesc logfile close
		  * @private
		  **/
		function logfile_close()
		{
			// If we've opened a file to log operations, we need to close it
			if(isset($this->logfilehdl) && is_resource($this->logfilehdl))
			{
				$msg = $this->msg.$this->lf_separator."### ".date("d/m/Y - H:i:s")." STOP LOGGING (Script execution time: ".$this->friendly_timer_str($this->dbgtimer->timer_get_current()).") ###".$this->lf_separator;
				fwrite($this->logfilehdl,$msg);
				fclose($this->logfilehdl);
				unset($this->logfilehdl);
			}
		}


		/** Very useful func for debugging purposes.
          * Slightly modified to suite the needs of my class.
          *
          * Original script:
          * @author: Stefan Eickhoff, URL: http://aktuell.de.selfhtml.org/artikel/php/variablen/
          *
		  * @shortdesc Func to output a better vardump. $OutputMode: 1=Browser; 2=Commandline-Window; 3=StringVar; 4=file;
		  * @public
		  **/
		function my_var_dump($v,$OutputMode=2,$fn="")
		{
	        // Ausgabe von var_dump über Output-Buffer in Variable einlesen
			ob_start();
		    var_dump($v);
			$content = ob_get_contents();
			ob_end_clean();

	        // maximale Einrueckung ermitteln
	        $m = 0;
	        preg_match_all('#^(.*)=>#mU', $content, $stack);
	        $lines = $stack[1];
	        $indents = array_map('strlen', $lines);
			if($indents) $m = max($indents) + 1;

	        // Ausgabe von var_dump() an maximaler Einrueckung ausrichten
            $content = @preg_replace('#^(.*)=>\\n\s+(\S)#eUm', '"\\1" .str_repeat(" ", $m - strlen("\\1")>1 ? $m - strlen("\\1") : 1). "\\2"', $content);

	        // bei Array-Strukturen oeffnende Klammer { in neue Zeile
			$content = preg_replace('#^((\s*).*){$#m', "\\1\n\\2{", $content);

			// pre tags entfernen
			$content = str_replace(array('<pre>','</pre>'),'',$content);

            switch($OutputMode)
            {
                case 1:
                    // Output to Browser-Window
                    echo '<pre style=" margin:10px 10px 10px; padding:10px 10px 10px 10px; background-color:#F2F2F2; color:#000; border:1px solid #333; font-family:\'Source Code Pro\', \'Lucida Console\', \'Courier\', monospace; font-size:12px; line-height:15px; overflow:visible;">'.$content.'</pre>';
                    break;
                case 2:
                    // Output to Commandline-Window or to Browser as hidden comment
                    echo isset($_SERVER['HTTP_HOST']) ? "\n<!--\n".$content."\n-->\n" : $content."\n";
                    break;
                case 3:
                    // Output into a StringVar
                    return '<pre style=" margin: 10px 10px 10px; padding: 10px 10px 10px 10px; background-color:#F2F2F2; color:#000; border: 1px solid #333; font-family:\'Source Code Pro\', \'Lucida Console\', \'Courier\', monospace; font-size:12px; line-height:15px; overflow: visible;">'.$content.'</pre>';
                    break;
                case 4:
                    // Output into a file, if a valid filename is given and we have write access to it
                    return $this->string2file(str_replace(array('&gt;','&quot;','&#10;'), array('>','"',''), strip_tags($content)),$fn,TRUE);
                    break;
            }
		}



	/////////////////////////////////////////////
	//	COMMANDLINE-UTILS



		/**
		  * @shortdesc returns the commandline arguments from argv separated in array with subarrays: input, commands, flags
          * @public
          *
          **/
		function cl_params()
		{
			$values = array();
			$result = array();
			$result['commands'] = array();
			$result['flags']    = array();
			$result['input']    = array();
			for($i=1;$i<$GLOBALS['argc'];$i++)
			{
				$values[$i] = trim($GLOBALS['argv'][$i]);
			}
			foreach($values as $val)
			{
				// testen auf Option mit Wertzuweisung
				if(preg_match('/=/',$val))
				{
					// options beginnend mit / oder - oder --
					if(substr($val,0,2)=='--')
					{
						$sep = '--';
					}
					elseif(substr($val,0,1)=='-')
					{
						$sep = '-';
					}
					elseif(substr($val,0,1)=='/')
					{
						$sep = '/';
					}
					else
					{
						// es ist nur eine Eingabe, die ein Gleichheitszeichen enthält,
						// keine Option mit Wertzuweisung
						$result['input'][] = $val;
						continue;
					}
					$tmp = explode('=',$val,2);
					$result['commands'][substr($tmp[0],strlen($sep))] = $tmp[1];
					continue;
				}
				// testen auf Flags und Optionen ohne Wertzuweisung
				if(substr($val,0,2)=='--')
				{
					$sep = '--';
				}
				elseif(substr($val,0,1)=='-')
				{
					$sep = '-';
				}
				elseif(substr($val,0,1)=='/')
				{
					$sep = '/';
				}
				else
				{
					// es ist nur eine Eingabe
					$result['input'][] = $val;
					continue;
				}
				$result['flags'][substr($val,strlen($sep))] = substr($val,strlen($sep));
				continue;
			}
			return $result;
		}


		/**
		  * $name = cl_ask("Question yes/no ?");
          *
		  * @shortdesc
          * @public
          *
          **/
		function cl_ask($q,$length='255')
		{
			echo "\n".$q."\n";
			$StdinPointer = fopen("php://stdin","r");
			$input = fgets($StdinPointer, $length);
			fclose($StdinPointer);
			unset($StdinPointer);
			return trim($input);
		}


		/**
		  * @shortdesc returns an file- or dirname from commandline arguments
          * @public
          *
          **/
		function cl_name_from_argv( $index=1, $dirname=false, $die_on_failure=false )
		{
			$name = $GLOBALS['argc'] > $index ? $GLOBALS['argv'][$index] : '';
			$success = $dirname===true ? ( $this->hn_is_dir($name) ? true : false ) : ( is_file($name) && is_readable($name) ? true : false );
//$this->my_var_dump( array(
//	'index'=>$index,
//	'dirname'=>$dirname,
//	'die_on_failure'=>$die_on_failure,
//	'argc'=>$GLOBALS['argc'],
//	'argv'=>$GLOBALS['argv'],
//	'name'=>$name,
//	'success'=>$success
//), 2 );
			if( $success )
			{
				return $name;
			}
			if( $die_on_failure===true )
			{
				$msg = $dirname===true ? "Es wurde kein korrekter Verzeichnisname uebergeben,\noder das Verzeichnis ist nicht lesbar." : "Es wurde kein korrekter Dateiname uebergeben,\noder die Datei ist nicht lesbar.";
				die( $msg . " (Rechtemanagement?):\n - $name" );
			}
			return false;
		}



	/////////////////////////////////////////////
	//	PHP-CONFIG + SYSINFOS

		/**
		  * @shortdesc checks if the script runs on a windows system
		  * @public
		  * @type bool
		  * @return bool
          *
		  **/
		function isWin()
		{
			return preg_match("/^Windows/",php_uname()) ? TRUE : FALSE;
		}


		/**
		  * @shortdesc checks if a file is in a folder of systempath. $file must be a basename.
		  * @public
		  * @type bool
		  * @return bool
          *
		  **/
		function isInSyspath($file,$returnpath=FALSE)
		{
			$separator = $this->isWin() ? ';' : ':';
			$a = explode($separator,getenv('PATH'));
			foreach($a as $path)
			{
				if(file_exists($this->hn_BuildFolderStr($path).$file))
				{
					return $returnpath ? $path : TRUE;
				}
			}
			return FALSE;
		}


	/////////////////////////////////////////////
	//	STRINGS


		/////////////////////////////////////////////
		//	BASIC STRING-PROCESSING

			/**
			  * @shortdesc changes Linebreaks into a defined string/char, default is a space
			  * @public
			  *
			  **/
			function nlReplace($string,$replace=' ')
			{
				return preg_replace('/\r\n|\r|\n/iS', $replace, $string);
			}

			/**
			  * @shortdesc does the opposite of nl2br
			  * @public
			  *
			  **/
			function br2nl($str)
			{
				return preg_replace("=<br(>|([\s/][^>]*)>)\r?\n?=i", "\n", $str);
			}


		/////////////////////////////////////////////
		//	STRINGS with DATE + TIME

			/** $s is something like "Mar 30 19:54"; or "Oct  1  2003"
			  *
			  * @shortdesc converts Date/Time-String from a FTP-RAW-List to a Unix-Timestamp
			  * @public
			  *

			  **/
			function date_ftpraw2timestamp($s)
			{
				$month = array();
				for($i=1;$i<=12;$i++) $month[date('M', mktime(5,5,5,$i,7))] = $i;

				$s = str_replace(array('    ','   ','  '),' ',$s);
				$d = explode(' ',$s);
				if(count($d)==3)
				{
					$M = $month[$d[0]];
					$t = explode(':',$d[2]);
					if(count($t)==2)
					{
						$jahr = intval(date('Y'));
						$schaltjahr = ((($jahr % 4) == 0) && (($jahr % 100) != 0) || (($jahr % 400) == 0));
						$schaltjahr = $schaltjahr && $M<3 ? false : $schaltjahr;  // wir ziehen nur einen zusaetzlichen Tag ab wenn wir uns schon nach dem 29.02. befinden
						$res = mktime($t[0],$t[1],0,$M,$d[1]);
						$res = (time() + (60*60*24)) > $res ? $res :  ($schaltjahr ? $res - (60 * 60 * 24 * 366) : $res - (60 * 60 * 24 * 365));  // evtl. 1 Jahr abziehen und dabei Schaltjahre beruecksichtigen
					}
					else
					{
						$res = mktime(0,0,0,$M,$d[1],$d[2]);
					}
					return $res;
				}
			}

			/**
			  * @shortdesc converts seconds (int) to a human readable time (string)
			  * @public
			  *
			  **/
			function friendly_timer_str($seconds,$lang='en')
			{
				$en = array();
					$en[1] = " seconds";
					$en[2] = " minutes";
					$en[3] = " hours";
				$de = array();
					$de[1] = " Sekunden";
					$de[2] = " Minuten";
					$de[3] = " Stunden";

				if($lang == 'de') $ext =& $de;
				else $ext =& $en;

				$seconds = (int)$seconds;
				$s = "";
				if($seconds <= (2*86400))
				{
					if($seconds <= 180 && $seconds >= 0)
					{
						if(round($seconds,3) > 0.0009)
						{
							$s = strval(round($seconds,3)) . " seconds";
						}
						else
						{
							$s = strval($seconds) . " seconds";
						}
					}
					else
					{
						$i			= 0;
						while($seconds >= pow(60,$i)) :
							$new_size = round($seconds / pow(60,$i),2);
							$i++;
						endwhile;
						$s = $new_size . $ext[$i];
					}
				}
				else
				{
					$s = strval(round(($seconds / 86400),1)) . " days";
				}
				return str_replace(".", ",", $s);
			}


			// Millisekunden nach mm:ss umrechnen
			function milliseconds_2_mmss($milliseconds, $separatorstring=':')
			{
				$nMSec = $milliseconds;
				$nSec = intval($nMSec / 1000);
				$nMin = intval($nSec / 60);
				$nSec -= ($nMin * 60);

				// Test auf "geschlabberte" 1/1000 sekunden
				$L = (($nMin * 60) + $nSec);
				$L = $L * 1000;

				if($nMSec > $L)
				{
					return str_pad($nMin,2,'0',STR_PAD_LEFT) . $separatorstring . str_pad($nSec,2,'0',STR_PAD_LEFT) . '.' . (str_pad(intval(($nMSec - $L) / 10),2,'0',STR_PAD_LEFT));
				}
				elseif($nMSec < $L)
				{
					return str_pad($nMin,2,'0',STR_PAD_LEFT) . $separatorstring . str_pad($nSec-1,2,'0',STR_PAD_LEFT) . '.' . (str_pad(intval(($L-$nMSec) / 10),2,'0',STR_PAD_LEFT));
				}
				else
				{
					return str_pad($nMin,2,'0',STR_PAD_LEFT) . $separatorstring . str_pad($nSec,2,'0',STR_PAD_LEFT) . '.00';
				}
			}


			// Sekunden nach mm:ss umrechnen
			function seconds_2_mmss($seconds, $separatorstring=':')
			{
				$res = $this->milliseconds_2_mmss($seconds * 1000, $separatorstring);
				$pos = strrpos($res,'.');
				return substr($res,0,$pos);
			}




		/////////////////////////////////////////////
		//	STRINGS with FILES & DIRs & URI's/URL's


			/** A Network-Resource like //MACHINE/Resource does not return TRUE when checking with 'is_dir' (on Windows)
              *
			  * @shortdesc checks if a given string is a directory on local machine or in local network
			  * @public
			  *
			  **/
			function hn_is_dir($dir)
			{
				$dir = $this->noTrailingSlash($dir);
				// checken ob der uebergebene string ein Dir ist auf dem lokalen Rechner, ...
				if(@is_dir($dir))
				{
					return TRUE;
				}
				if(@is_file($dir))
				{
					return FALSE;
				}
				// ... oder im lokalen Netzwerk
				$hdl = @opendir($dir);
				if($hdl !== FALSE)
				{
					closedir($hdl);
					return TRUE;
				}
				return FALSE;
			}


			function noTrailingSlash($dir)
			{
				$dir = $this->noBacks($dir);
				return substr($dir,strlen($dir)-1,1)=='/' ? substr($dir,0,strlen($dir)-1) : $dir;
			}

			/** Some directory- and file-funcs in some (not all) PHP-Versions on Windows have backslashes in their returns,
			  * also if you pass only strings with forwardslashes!
			  * I use this func to correct this behave.
              *
			  * @shortdesc corrects BackSlashes to ForwardSlashes
			  * @public
			  *
			  **/
			function noBacks($PathStr)
			{
				return str_replace("\\","/",$PathStr);
			}


			/**
			  * @shortdesc concatenates 2 strings and/or add a trailing slash if needed
			  * @public
			  *
			  **/
			function hn_BuildFolderStr($Folder,$Subfolder="")
			{
				$Folder = $this->noBacks($Folder);
				$lastChar = substr($Folder,strlen($Folder)-1,1);
				$Folder .= $lastChar <> "/" ? "/" : "";
				if(trim($Subfolder) != "")
				{
					$Subfolder = $this->noBacks($Subfolder);
					$lastChar = substr($Subfolder,strlen($Subfolder)-1,1);
					$Subfolder .= $lastChar <> "/" ? "/" : "";
				}
				return $Folder.$Subfolder;
			}



	function hn_BuildDirStr($dir_in,$part='fullname')
	{
		$obj = new hn_path_object();
		$res = $obj->dir_get_part($dir_in,$part);
		unset($obj);
		return $res;
	}


	function hn_BuildFileStr($file_in,$part='fullname')
	{
		$obj = new hn_path_object();
		$res = $obj->file_get_part($file_in,$part);
		unset($obj);
		return $res;
	}







			/**
			  * @shortdesc return the extension of a file (or only a given string)
			  * @public
			  *
			  **/
			function extension($file,$OnlyCheckString=FALSE)
			{
				if($OnlyCheckString)
				{
					return substr(strrchr(basename($file),'.'),1);
				}
				if(!file_exists($file))
				{
					return FALSE;
				}
				$fileInfo = pathinfo($file);
				if(isset($fileInfo['extension']))
				{
					return strlen(trim($fileInfo['extension'])) > 0 ? $fileInfo['extension'] : '';
				}
				else
				{
					return '';
				}
			}



			/**
			  * @shortdesc returns the last subfolder from a given dir-string
			  * @public
			  *
			  **/
			function LastPathsegment($Pathname)
			{
				$Pathname = $this->nobacks(trim($Pathname));
				if($Pathname === '/')
				{
					return '/';
				}
				if(strrchr($Pathname,'/') === '/')
				{
					$Pathname = substr($Pathname,0,strlen($Pathname)-1);
				}
				if(is_file($Pathname))
				{
					$PathSegments = explode('/',dirname($Pathname));
				}
				#if($this->hn_is_dir($Pathname))
				#{
				#	$PathSegments = explode('/',$Pathname);
				#}
				else
				{
					// ist keine lokale Datei, wir verlassen uns darauf das
					// wirklich ein Verzeichnis uebergeben wurde, falls es auch kein lokales Verzeichnis ist
					$PathSegments = explode('/',$Pathname);
				}
				return end($PathSegments);
			}



			/** in some (not all) PHP-Versions on Windows some (not all) dir-funcs return lowercase-chars for uppercase-chars, :(
			  *
			  * @shortdesc converts a pathstring to an absolute URL-string, if the directory is in servers DocRoot
			  * @public
			  *
			  **/
			function path2url($fullpathfilename)
			{
				$a = $this->isWin() ? strtolower(substr($fullpathfilename,0,strlen($_SERVER['DOCUMENT_ROOT']))) : substr($fullpathfilename,0,strlen($_SERVER['DOCUMENT_ROOT']));
				$b = $this->isWin() ? strtolower($_SERVER['DOCUMENT_ROOT']) : $_SERVER['DOCUMENT_ROOT'];
				if($a == $b)
				{
					if(is_file($fullpathfilename))
					{
						$d = substr(dirname($fullpathfilename), strlen($_SERVER['DOCUMENT_ROOT']));
						return $this->hn_BuildFolderStr($this->noBacks($d)).basename($fullpathfilename);
					}
					elseif(@is_dir($fullpathfilename))
					{
						$s = substr($fullpathfilename, strlen($_SERVER['DOCUMENT_ROOT']));
						return $this->hn_BuildFolderStr($this->noBacks($s));
					}
				}
				else
				{
					return FALSE;
				}
			}


			/**
			  * @shortdesc converts a filesize from (int) bytes into a (for human better readable) string
			  * @public
			  *
			  **/
			function friendly_filesize( $filesize )
			{
				$unit = array('',' B',' KB',' MB',' GB',' TB');
				$j = 0;
				while($filesize >= pow(1024,$j))
				{
					$j++;
				}
				$filesize = round($filesize / pow(1024,$j-1) * 100) / 100 . $unit[$j];
				return str_replace('.', ',', $filesize);
			}



			// MimeType

			function get_mime_type($filename)
			{
				$ext = strtolower($this->extension($filename,TRUE));
				return isset($this->MIMETYPES[$ext]) ? $this->MIMETYPES[$ext] : 'application/octet-stream';
			}


	/////////////////////////////////////////////
	//	ASCII-FILE-PROCESSING

		/** @shortdesc opens a file with locking
		  *
		  **/
		function flock_load_file($filename, $writemode=FALSE)
		{
			$id = str_replace(array(':', "\\", '/', "'", '"', ' '), '_', strtolower($filename));
			$mode = $writemode ? 'rb+' : 'rb';
			$lock = $writemode ? LOCK_EX : LOCK_SH;
			@touch($filename);
			$this->lock_handles[$id] = @fopen($filename, $mode);
			if(!is_resource($this->lock_handles[$id]))
			{
				unset($this->lock_handles[$id]);
				return FALSE;
			}
			if(!flock($this->lock_handles[$id], $lock))
			{
				unset($this->lock_handles[$id]);
				return FALSE;
			}
			$buffer = '';
			while(!feof($this->lock_handles[$id]))
			{
				$buffer .= fread($this->lock_handles[$id], 8192);
			}
			if(!$writemode)
			{
				fclose($this->lock_handles[$id]);
				unset($this->lock_handles[$id]);
			}
			return $buffer;
		}

		/** @shortdesc writes to a file that was opened with locking before
		  *
		  **/
		function flock_save_file($filename, $content)
		{
			$id = str_replace(array(':', "\\", '/', "'", '"', ' '), '_', strtolower($filename));
			if(!isset($this->lock_handles[$id]) || !is_resource($this->lock_handles[$id]))
			{
				unset($this->lock_handles[$id]);
				return FALSE;
			}
			rewind($this->lock_handles[$id]);
			ftruncate($this->lock_handles[$id], 0);
			fwrite($this->lock_handles[$id], $content, strlen($content));
			fclose($this->lock_handles[$id]);
			unset($this->lock_handles[$id]);
			return TRUE;
		}



		/** @shortdesc creates an otl-file (*.otl), readable with e.g. NoteTab. It needs the Fullpathfilename with or without extension, and an array with keys = headingname and values = content
		  * @public
		  **/
		function make_otl_file($fullpath,$contentarray,$sorting=FALSE)
		{
			if(strtolower($this->extension($fullpath,TRUE))!='otl') $fullpath .= '.otl';
			$content = $sorting ? "= V4 Outline MultiLine TabWidth=30\r\n\r\n" : "= V4 Outline MultiLine NoSorting TabWidth=30\r\n\r\n";
			foreach($contentarray as $k=>$v)
			{
				$content .= "H=\"$k\"\r\n$v\r\n\r\n";
			}
			return $this->string2file($content,$fullpath) ? $fullpath : FALSE;
		}



		/** @shortdesc opens a file, processes str_replace on the content and save the new content to same file
		  * @public
		  **/
		function file_str_Replace($search,$replace,$filename)
		{
			//$this->string2file(str_replace($search,$replace,$this->file2string($filename)),$filename);
            // OneLiner der aber immer speichert, und somit auch Datei-lastmodified ändert wenn nichts geändert wurde
			$s1 = $this->file2string($filename);
			$s2 = str_replace($search,$replace,$s1);
			$same = strpos($s1,$s2);
			if($same===FALSE)
			{
				return $this->string2file($s2,$filename);
			}
			elseif($same===0)
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		}

		/** @shortdesc opens a file, processes preg_replace on the content and save the new content to file
		  * @public
		  **/
		function file_preg_Replace($pattern,$replace,$filename)
		{
			//$this->string2file(preg_replace($pattern,$replace,$this->file2string($filename)),$filename);
			$s1 = $this->file2string($filename);
			$s2 = preg_replace($pattern,$replace,$s1);
			$same = strpos($s1,$s2);
			if($same===FALSE)
			{
				return $this->string2file($filename);
			}
			elseif($same===0)
			{
				return TRUE;
			}
			else
			{
				return FALSE;
			}
		}



		/** @shortdesc reads a file into a stringvar
		  * @public
		  **/
		function file2string($filename,$readmode="rb")
		{
			if(!is_file($filename) || !is_readable($filename))
			{
				$this->msg = 'ERROR: NO (readable) FILE:  $hn_basic->file2string('.$filename.')';
				$this->debug('error,hn_basic,file');
				return FALSE;
			}
			if(strcmp('4.3.0', phpversion()) < 0)
			{
				return file_get_contents($filename);
			}
			$fp = @fopen($filename, $readmode);
			if($fp===FALSE)
			{
				$this->msg = 'ERROR: NO STREAM:  $hn_basic->file2string('.$filename.')';
				$this->debug('error,hn_basic,file');
				return FALSE;
			}
			$size = @filesize($filename);
			clearstatcache();
			if($size<=0)
			{
				@fclose($fp);
				return '';
			}
			$s = @fread($fp, $size);
			@fclose($fp);
			return $s;
		}

		/** @shortdesc safes a string to a file
		  * @public
		  **/
		function string2file($s,$filename,$APPEND=FALSE,$BINARY=TRUE)
		{
			if(function_exists('file_put_contents'))
			{
				$flags = $APPEND ? FILE_APPEND + LOCK_EX : LOCK_EX;
				$res = @file_put_contents($filename,$s,$flags) === FALSE ? FALSE : TRUE;
				if(!$res)
				{
					$this->msg = 'ERROR: NO STREAM: $hn_basic->string2file($s,'.$filename.')';
					$this->debug('error,hn_basic,file');
				}
				return $res;
			}
			// Fallback for PHP4
			if($APPEND && (!is_file($filename) || !is_writable($filename))) @touch($filename);
			if($APPEND && (!is_file($filename) || !is_writable($filename)))
			{
				$this->msg = 'ERROR: APPEND $hn_basic->string2file('.$filename.')';
				$this->debug('error,hn_basic,file');
				return FALSE;
			}
			$mode = $APPEND ? 'a' : 'w';
			$mode = $BINARY ? $mode.'b' : $mode;
			$fp = @fopen($filename, $mode);
			if($fp===FALSE)
			{
				$this->msg = 'ERROR: NO STREAM: $hn_basic->string2file($s,'.$filename.')';
				$this->debug('error,hn_basic,file');
				return FALSE;
			}
			fwrite($fp,$s);
			fclose($fp);
			return TRUE;
		}



		/** @shortdesc safes a string-Array to a file
		  * @public
		  **/
		function array2file($a,$filename,$APPEND=FALSE,$BINARY=TRUE)
		{
			$linebreak = $this->isWin() ? "\r\n" : "\n";
			$s = join($linebreak,$a);
			return $this->string2file($s,$filename,$APPEND,$BINARY);
		}

		/** @shortdesc reads a file line by line into a string-array
		  * @public
		  **/
		function file2array($filename,$KeepLineEndings=FALSE)
		{
				if(!is_file($filename) || !is_readable($filename))
				{
					$this->msg = 'ERROR: NO FILE:  $hn_basic->file2array('.$filename.')';
					$this->debug('error,hn_basic,file');
					return FALSE;
				}
			if($KeepLineEndings)
			{
				return @file($filename);
			}
			else
			{
				$s = $this->file2string($filename);
				$s = str_replace("\r\n", "\n", $s);
				return explode("\n", trim($s));
				# OLD:
				#$t = @file($filename);
				#foreach($t as $k=>$v)
				#{
				#	$t[$k] = trim($v);
				#}
				#return $t;
			}
		}



		/** @shortdesc reads a gzcompressed file into a (uncompressed) stringvar
		  * @public
		  **/
		function gzfile2string($filename,$readmode='rb')
		{
				if(!is_file($filename) || !is_readable($filename))
				{
					$this->msg = 'ERROR: NO (readable) FILE:  $hn_basic->gzfile2string('.$filename.')';
					$this->debug('error,hn_basic,file');
					return FALSE;
				}
			$fp = @gzopen($filename, $readmode);
			if($fp===FALSE)
			{
				$this->msg = 'ERROR: NO STREAM:  $hn_basic->gzfile2string('.$filename.')';
				$this->debug('error,hn_basic,file');
				return FALSE;
			}
			$s = '';
			while(!gzeof($fp)) $s .= gzread($fp, 1024);
			gzclose($fp);
			return $s;
		}

		/** @shortdesc reads a gzcompressed file line by line into a (uncompressed) string-array
		  * @public
		  **/
		function gzfile2array($filename)
		{
				if(!is_file($filename) || !is_readable($filename))
				{
					$this->msg = 'ERROR: NO FILE:  $hn_basic->file2array('.$filename.')';
					$this->debug('error,hn_basic,file');
					return FALSE;
				}
			return @gzfile($filename);
		}

		/** @shortdesc safes a string to a gzcompressed file
		  * @public
		  **/
		function string2gzfile($s,$filename,$complevel=FALSE)
		{
			$fp = @gzopen($filename, 'wb'.$complevel);
			if($fp===FALSE)
			{
				$this->msg = 'ERROR: NO STREAM:  $hn_basic->string2gzfile('.$filename.')';
				$this->debug('error,hn_basic,file');
				return FALSE;
			}
			gzwrite($fp, $s);
			gzclose($fp);
			return TRUE;
		}

		/** @shortdesc safes a string-Array to a gzcompressed file
		  * @public
		  **/
		function array2gzfile($a,$filename,$complevel=FALSE)
		{
			$s = implode('',$a);
			return $this->string2gzfile($s,$filename,$complevel);
		}



// NEW-METHODS: 03.10.2008 - 14:08:58 >>> //

		/** @shortdesc safes a string to a UTF_8-file
		  * @public
		  **/
		function string2UTF8file($s,$filename,$APPEND=FALSE)
		{
				if($APPEND && (!is_file($filename) || !is_writable($filename))) @touch($filename);
				if($APPEND && (!is_file($filename) || !is_writable($filename)))
				{
					$this->msg = 'ERROR: APPEND $hn_basic->string2UTF8file('.$filename.')';
					$this->debug('error,hn_basic,file');
					return FALSE;
				}
			$mode = $APPEND ? 'ab' : 'wb';
			$fp = @fopen($filename, $mode);
			if($fp===FALSE)
			{
				$this->msg = 'ERROR: NO STREAM: $hn_basic->string2UTF8file($s,'.$filename.')';
				$this->debug('error,hn_basic,file');
				return FALSE;
			}
			fwrite($fp,utf8_encode($s));
			fclose($fp);
			return TRUE;
		}

		/** @shortdesc reads a UTF_8-file into a stringvar
		  * @public
		  **/
		function UTF8file2string($filename)
		{
			if(!is_file($filename) || !is_readable($filename))
			{
				$this->msg = 'ERROR: NO (readable) FILE:  $hn_basic->UTF8file2string('.$filename.')';
				$this->debug('error,hn_basic,file');
				return FALSE;
			}
			$fp = @fopen($filename, 'rb');
			if($fp===FALSE)
			{
				$this->msg = 'ERROR: NO STREAM:  $hn_basic->UTF8file2string('.$filename.')';
				$this->debug('error,hn_basic,file');
				return FALSE;
			}
			$size = @filesize($filename);
			clearstatcache();
			if($size<=0)
			{
				@fclose($fp);
				return '';
			}
			$s = utf8_decode(@fread($fp, $size * 4));
			@fclose($fp);
			return $s;
		}



		/** @shortdesc reads a file into a base64encoded stringvar
		  * @public
		  **/
		function file2string_B64($filename,$RFC_2045=TRUE)
		{
			$s = $this->file2string($filename,'rb');
			if($s===FALSE)
			{
				return FALSE;
			}
			$b64 = base64_encode($s);
			return $RFC_2045===TRUE ? chunk_split($b64) : $b64;
		}

		/** @shortdesc reads a (base64encoded-)file into a base64decoded stringvar
		  * @public
		  **/
		function B64_file2string($filename)
		{
			$s = $this->file2string($filename,'rb');
			if($s===FALSE)
			{
				return FALSE;
			}
			return base64_decode(str_replace("/r/n","",$s));
		}

// <<< NEW-METHODS: 03.10.2008 - 14:08:58 //




	/////////////////////////////////////////////
	//	ARRAYS

		// ARRAY-SORTING

                function sort_IPv4($array_with_ips, $descending=FALSE)
                {
                    if(!is_array($array_with_ips))
                    {
                        return false;
                    }
                    if(count($array_with_ips)<1)
                    {
                        return $array_with_ips;
                    }
                    $tmp = array();
                    foreach($array_with_ips as $s)
                    {
                        $a = explode('.',trim($s));
                        if(count($a)<>4)
                        {
                            continue;
                        }
                        $tmp[$s] = array('p1'=>intval($a[0]), 'p2'=>intval($a[1]), 'p3'=>intval($a[2]), 'p4'=>intval($a[3]), 'ip'=>"$s");
                    }
                    $tmp = $this->hn_array_sort($tmp, array('p1','p2','p3','p4'), true, $descending);
                    $res = array();
                    foreach($tmp as $a)
                    {
                        $res[] = strval($a['p1'].'.'.$a['p2'].'.'.$a['p3'].'.'.$a['p4']);
                    }
                    return $res;
                }




			// by values:

				/** Sorts the values of a given multidimensional Array in hirarchical order of the sortlist
				  *
				  * @shortdesc USAGE: $SORTED_Array = hn_array_sort($orig_array, array('field3','field1','field2'));
				  * @public
				  *
				  **/
				function hn_array_sort($a,$sl,$intvals=FALSE,$descending=FALSE,$casesensitive=TRUE)
				{
					$GLOBALS['__HN_SORTVALUE_LIST_DESCENDING__'] = $descending;
					$GLOBALS['__HN_SORTVALUE_LIST_CASESENSITIVE__'] = $casesensitive;
					$GLOBALS['__HN_SORTVALUE_LIST__'] = $sl;
					if($intvals===TRUE)
					{
						usort($a, array(&$this, 'hn_cmp_Values_intvals'));
					}
					else
					{
						usort($a, array(&$this, 'hn_cmp_Values_strvals'));
					}
					return $a;
				}
					// Callback-func for hn_array_sort()
					/** @private **/
					function hn_cmp_Values_strvals($a,$b)
					{
						foreach($GLOBALS['__HN_SORTVALUE_LIST__'] as $f)
						{
							$strc = $GLOBALS['__HN_SORTVALUE_LIST_CASESENSITIVE__']===TRUE ? strcmp($a[$f],$b[$f]) : strcasecmp($a[$f],$b[$f]);
							if($strc != 0)
							{
								if($GLOBALS['__HN_SORTVALUE_LIST_DESCENDING__']===TRUE)
								{
									return $strc * (-1);
								}
								return $strc;
							}
						}
						return 0;
					}
					// Callback-func for hn_array_sort()
					/** @private **/
					function hn_cmp_Values_intvals($a,$b)
					{
						foreach($GLOBALS['__HN_SORTVALUE_LIST__'] as $f)
						{
							if(intval($a[$f])===intval($b[$f]))
							{
								$intc = 0;
							}
							elseif(intval($a[$f])>intval($b[$f]))
							{
								$intc = $GLOBALS['__HN_SORTVALUE_LIST_DESCENDING__']===TRUE ? -1 : 1;
							}
							else
							{
								$intc = $GLOBALS['__HN_SORTVALUE_LIST_DESCENDING__']===TRUE ? 1 : -1;
							}
							if($intc !== 0) return $intc;
						}
						return 0;
					}


            // by keys:

			/**
			  * Sorts the keys of a given Array.
			  * USAGE: $SORTED_Array = hn_arrayKey_sort($orig_array [, TRUE | FALSE ]);
			  *
			  * @shortdesc Sorts a multidimensional array
			  **/
			function hn_arrayKey_sort($a,$descending=FALSE,$intvals=FALSE)
			{
				$GLOBALS['__HN_SORTKEY_DESC__'] = (bool)$descending;
				if($intvals===TRUE)
				{
					uksort($a, array(&$this, 'hn_cmp_Keys_intvals'));
				}
				else
				{
					uksort($a, array(&$this, 'hn_cmp_Keys_strvals'));
				}
				return $a;
			}
				/** @shortdesc Callback-func for hn_arrayKey_sort()
				  **/
				function hn_cmp_Keys_intvals($a,$b)
				{
					if((int)$a == (int)$b) return 0;
					if((bool)$GLOBALS['__HN_SORTKEY_DESC__'])
					{
						return ((int)$a > (int)$b) ? -1 : 1;
					}
					else
					{
						return ((int)$a > (int)$b) ? 1 : -1;
					}
				}
				/** @shortdesc Callback-func for hn_arrayKey_sort()
				  **/
				function hn_cmp_Keys_strvals($a,$b)
				{
					if($a===$b) return 0;
					if((bool)$GLOBALS['__HN_SORTKEY_DESC__'])
					{
						return ($a > $b) ? -1 : 1;
					}
					else
					{
						return ($a > $b) ? 1 : -1;
					}
				}



			/**
			  * @shortdesc returns one or more random entries from a given array
			  * @public
              * @type array
              * @return array with random selection
			  *
			  **/
			function Random_MultiDimArray_Items(&$Array,$ReturnedItems=1)
			{
				$RandomSelection = array();
				if(count($Array) >= $ReturnedItems)
				{
					srand((double)microtime() * 10000000);
					if($ReturnedItems < 2)
					{
						$pickOne = array_rand($Array, 1);
						$RandomSelection = $Array[$pickOne];
					}
					else
					{
						$picksome = array_rand($Array, $ReturnedItems);
						foreach($picksome as $v)
						{
							$RandomSelection[$v] = $Array[$v];
						}
					}
				}
				else
				{
					$RandomSelection = $Array;
				}
				return $RandomSelection;
			}



			/**
			  * @shortdesc returns one or more random entries from a given array
			  * @public
              * @type array
              * @return array with random selection
			  *
			  **/
			function RandomArrayItems($Array_input,$ReturnedItems=1)
			{
				if(count($Array_input) === $ReturnedItems)
				{
					return $Array_input;
				}
				$ReturnedItems -= 1;
				$Array_output = array();
				mt_srand((double) microtime() * 1000000);
				for($i=0; $i<=$ReturnedItems; $i++)
				{
					$Array_output[] = $Array_input[mt_rand(0, count($Array_input)-1)];
				}
				return $Array_output;
			}
				#	srand((double)microtime() * 10000000);
				#	if($ReturnedItems < 2)
				#	{
				#		$pickOne = array_rand($Array, 1);
				#		$RandomSelection = $Array[$pickOne];
				#	}
				#	else
				#	{
				#		$picksome = array_rand($Array, $ReturnedItems);
				#		foreach($picksome as $v)
				#		{
				#			$RandomSelection[$v] = $Array[$v];
				#		}
				#	}
				#}
				#else
				#{
				#	$RandomSelection = $Array;
				#}
				#return $RandomSelection;
				#


	/////////////////////////////////////////////
	//	CACHING & ARRAYS


		/////////////////////////////////////////////
		//	Arrays und Objekte per un/serialize speichern/lesen.

			/**
			  * @shortdesc filles a given Array with Values from a (serialized) cachefile
			  * @public
              * @return TRUE, if an array could build, means if the file was available and it'S data was successfully read
			  *
			  **/
			function GetCacheArray(&$Array,$filename)
			{
				$s = $this->file2string($filename);
				if(trim($s)=='') return FALSE;
				$Array = unserialize($s);
				return is_array($Array);
			}

			/**
			  * @shortdesc writes (serialized) data of a given Array into a cachefile
			  * @public
              * @return TRUE, if the file was build
			  *
			  **/
			function SetCacheArray(&$Array,$filename)
			{
				$cache_data = serialize($Array);
				return $this->string2file($cache_data,$filename);
			}


		/////////////////////////////////////////////
		//	OUTPUT-BUFFERING

			/**
			  * @shortdesc starts an outputbuffer and otionally sends HTTP-headers to avoid caching the data by browsers / proxies
			  * @public
			  *
			  **/
			function start_output( $HeadersForNoCache=FALSE )
			{
				// Output-Buffering starten
				ob_start();
				ob_implicit_flush(0);

				// Header gegen uebereifrige Caches senden
				if(!headers_sent() && $HeadersForNoCache) {
					header ("expires: Sun, 06 Jan 2002 01:00:00 GMT");                 // Datum in der Vergangenheit
					header ("Last-Modified: " . gmdate ("D, d M Y H:i:s") . " GMT");   // immer geaendert
					header ("pragma: no-cache");                                       // HTTP/1.0
					header ("Cache-Control: no-cache, must-revalidate");               // HTTP/1.1
				}
			}

			/**
			  * @shortdesc stops outputbuffering and return buffer as string or otionally flush it with gzip-compression
			  * @public
			  *
			  **/
			function end_output( $compress=false )
			{
				$HTTP_ACCEPT_ENCODING = $_SERVER["HTTP_ACCEPT_ENCODING"];
				if( strpos( $HTTP_ACCEPT_ENCODING, 'x-gzip' ) !== false )
				{
					$encoding = 'x-gzip';
				}
				elseif( strpos( $HTTP_ACCEPT_ENCODING, 'gzip' ) !== false )
				{
					$encoding = 'gzip';
				}
				else
				{
					$encoding = false;
				}

				// Output-Buffering beenden
				$contents = ob_get_contents();
				ob_end_clean();

				if( $compress===true && $encoding!==false )
				{
					$len = strlen($contents);
					if( $len < 2048 )
					{
						// no need to waste resources in compressing very little data
						return $contents;
					}
					else
					{
						// Browser kann komprimierte Daten geliefert bekommen
						header('Content-Encoding: '.$encoding);      // Content-Encoding senden (damit der Browser was merkt)
						echo "\x1f\x8b\x08\x00\x00\x00\x00\x00";     // hmm, gzip-start?
						$size = strlen($contents);                   // Grösse bestimmen
						$crc = crc32($contents);                     // Checksumme bestimmen
						$contents = gzcompress($contents, 9);        // Komprimieren
						$contents = substr($contents, 0, $len - 4);  // letzte 4 Bytes abschneiden
						echo $contents;                              // komprimiertes Zeugs ausgeben
						gzip_PrintFourChars($crc);                   // Checksumme ausgeben
						gzip_PrintFourChars($size);                  // Größe ausgeben
					}
				}
				else
				{
					return $contents;                          // Inhalt unkomprimiert zurueckgeben
				}
			}





	/////////////////////////////////////////////
	//	SONSTIGES


			function new_password( $length=8, $aChars=array() )
			{
				$pass = '';
				srand((float) microtime() * 10000000);
  				$chars = array_merge( range(1,9), range('a','z'), range('A','Z'), $aChars );
    			shuffle( $chars );
				for( $i=0; $i<$length; $i++ )
				{
					$pass .= $chars[ rand( 0, count($chars)-1 ) ];
				}
  				return $pass;
			}



		/////////////////////////////////////////////
		//	COUNTER with TEXTFILE

			/**
			  * @shortdesc Store/Retrieve a counter-value in/from a textfile. Optionally count it up or store a, in third param specified, value.
			  * @public
			  * @type integer
			  * @return counter-value or FALSE
			  *
			  **/
			function txt_counter($filename,$add=FALSE,$fixvalue=FALSE)
			{
				if(!is_file($filename))
				{
					if(!@touch($filename))
					{
						return FALSE;
					}
				}
				if(!is_readable($filename))
				{
					return FALSE;
				}
				$add = $add===true ? true : false;
				if($add && !is_writable($filename))
				{
					return FALSE;
				}

				$orig_abo = ignore_user_abort(true);

// DONE 4 -c protection: (function txt_counter) hier muss noch flock() hin !!

				$fp = $this->flock_load_file($filename,$add);
				if($fp === false)
				{
					ignore_user_abort($orig_abo);
					return FALSE;
				}

				$counter = intval(trim($fp));

				if(!$add)
				{
					ignore_user_abort($orig_abo);
					return $counter;
				}

				$counter = is_numeric($fixvalue) ? intval($fixvalue) : intval($counter + 1);

				$fp = $this->flock_save_file($filename,$counter);
				if($fp !== true)
				{
					ignore_user_abort($orig_abo);
					return FALSE;
				}

				return $counter;
			}


			/**
			  * @shortdesc Test against all relevant needs of txt_counter-func for a given filename.
			  * @public
			  * @type boolean
			  * @return TRUE or FALSE
			  *
			  **/
			function txt_counter_check($counter_filename)
			{
				// retrieve current counter-value
				$old = $this->txt_counter($counter_filename);
				if($old===false)
				{
					return false;
				}
				// Count up one and retrieve new counter-value
				$new = $this->txt_counter($counter_filename,true);
				// check if counter works correct
				if(($new !== false) && ($new - $old == 1))
				{
					return $this->txt_counter($counter_filename,true,intval($old)) === intval($old) ? true : false;
				}
				return false;
			}

	// some more little helpers

		/**
		 * This script is supposed to be used together with the HTML2FPDF.php class
		 * Copyright (C) 2004-2005 Renato Coelho
		 *
		 * returns an associative array (keys: R,G,B) from html code (e.g. #3FE5AA)
		 **/
		function ConvertColor($color="#000000")
		{
			//W3C approved color array (disabled)
			//static $common_colors = array('black'=>'#000000','silver'=>'#C0C0C0','gray'=>'#808080', 'white'=>'#FFFFFF','maroon'=>'#800000','red'=>'#FF0000','purple'=>'#800080','fuchsia'=>'#FF00FF','green'=>'#008000','lime'=>'#00FF00','olive'=>'#808000','yellow'=>'#FFFF00','navy'=>'#000080', 'blue'=>'#0000FF','teal'=>'#008080','aqua'=>'#00FFFF');
			//All color names array
			static $common_colors = array('antiquewhite'=>'#FAEBD7','aquamarine'=>'#7FFFD4','beige'=>'#F5F5DC','black'=>'#000000','blue'=>'#0000FF','brown'=>'#A52A2A','cadetblue'=>'#5F9EA0','chocolate'=>'#D2691E','cornflowerblue'=>'#6495ED','crimson'=>'#DC143C','darkblue'=>'#00008B','darkgoldenrod'=>'#B8860B','darkgreen'=>'#006400','darkmagenta'=>'#8B008B','darkorange'=>'#FF8C00','darkred'=>'#8B0000','darkseagreen'=>'#8FBC8F','darkslategray'=>'#2F4F4F','darkviolet'=>'#9400D3','deepskyblue'=>'#00BFFF','dodgerblue'=>'#1E90FF','firebrick'=>'#B22222','forestgreen'=>'#228B22','gainsboro'=>'#DCDCDC','gold'=>'#FFD700','gray'=>'#808080','green'=>'#008000','greenyellow'=>'#ADFF2F','hotpink'=>'#FF69B4','indigo'=>'#4B0082','khaki'=>'#F0E68C','lavenderblush'=>'#FFF0F5','lemonchiffon'=>'#FFFACD','lightcoral'=>'#F08080','lightgoldenrodyellow'=>'#FAFAD2','lightgreen'=>'#90EE90','lightsalmon'=>'#FFA07A','lightskyblue'=>'#87CEFA','lightslategray'=>'#778899','lightyellow'=>'#FFFFE0','limegreen'=>'#32CD32','magenta'=>'#FF00FF','mediumaquamarine'=>'#66CDAA','mediumorchid'=>'#BA55D3','mediumseagreen'=>'#3CB371','mediumspringgreen'=>'#00FA9A','mediumvioletred'=>'#C71585','mintcream'=>'#F5FFFA','moccasin'=>'#FFE4B5','navy'=>'#000080','olive'=>'#808000','orange'=>'#FFA500','orchid'=>'#DA70D6','palegreen'=>'#98FB98','palevioletred'=>'#D87093','peachpuff'=>'#FFDAB9','pink'=>'#FFC0CB','powderblue'=>'#B0E0E6','red'=>'#FF0000','royalblue'=>'#4169E1','salmon'=>'#FA8072','seagreen'=>'#2E8B57','sienna'=>'#A0522D','skyblue'=>'#87CEEB','slategray'=>'#708090','springgreen'=>'#00FF7F','tan'=>'#D2B48C','thistle'=>'#D8BFD8','turquoise'=>'#40E0D0','violetred'=>'#D02090','white'=>'#FFFFFF','yellow'=>'#FFFF00');
			//http://www.w3schools.com/css/css_colornames.asp
			if ( ($color{0} != '#') and ( strstr($color,'(') === false ) ) $color = $common_colors[strtolower($color)];

			if($color{0} == '#') //case of #nnnnnn or #nnn
			{
				$cor = strtoupper($color);
				if(strlen($cor) == 4) // Turn #RGB into #RRGGBB
				{
					$cor = "#" . $cor{1} . $cor{1} . $cor{2} . $cor{2} . $cor{3} . $cor{3};
				}
				$R = substr($cor, 1, 2);
				$vermelho = hexdec($R);
				$V = substr($cor, 3, 2);
				$verde = hexdec($V);
				$B = substr($cor, 5, 2);
				$azul = hexdec($B);
				$color = array();
				$color['R']=$vermelho;
				$color['G']=$verde;
				$color['B']=$azul;
			}
			else //case of RGB(r,g,b)
			{
				$color = str_replace(array('rgb','RGB','(',')'), '', $color);
				$cores = explode(',', $color);
				$color = array();
				$color['R']=intval(trim($cores[0]));
				$color['G']=intval(trim($cores[1]));
				$color['B']=intval(trim($cores[2]));
			}

			// array['R']['G']['B']
			return empty($color) ? array('R'=>0,'G'=>0,'B'=>0) : $color;
		}


		/**
		 * Depends of maxsize value to make % work properly. Usually maxsize == pagewidth
		 *
		 **/
		function ConvertSize($size=5,$maxsize=0)
		{
			//Identify size (remember: we are using 'mm' units here)
			if ( stristr($size,'px') ) $size *= 0.2645;       //pixels
			elseif ( stristr($size,'cm') ) $size *= 10;       //centimeters
			elseif ( stristr($size,'mm') ) $size += 0;        //millimeters
			elseif ( stristr($size,'in') ) $size *= 25.4;     //inches
			elseif ( stristr($size,'pc') ) $size *= 38.1/9;   //PostScript picas
			elseif ( stristr($size,'pt') ) $size *= 25.4/72;  //72dpi
			elseif ( stristr($size,'%') )
			{
				$size += 0; //make "90%" become simply "90"
				$size *= $maxsize/100;
			}
			else $size *= 0.2645; //nothing == px

			// size in millimeters (mm)
			return $size;
		}



	// STRINGS

		function txtentities($html)
		{
			$trans = get_html_translation_table(HTML_ENTITIES);
			$trans = array_flip($trans);
			return strtr($html, $trans);
		}


// NEW-METHODS: MEMORY-USAGE  [13.10.2011 - 13:40:57] >>>


		function get_memory_limit($userfriendly=FALSE)
		{
			$s = ini_get('memory_limit');
			preg_match('/^[0-9].*([k|K|m|M|g|G])$/',$s,$match);
			$char = isset($match[1]) ? $match[1] : '';
			switch(strtoupper($char))
			{
				case 'G':
					$i = intval(str_replace(array('G','g'),'',$s)) * 1073741824;
					break;
				case 'M':
					$i = intval(str_replace(array('M','m'),'',$s)) * 1048576;
					break;
				case 'K':
					$i = intval(str_replace(array('K','k'),'',$s)) * 1024;
					break;
				default:
					$i = intval($s);
			}
			return $userfriendly ? $this->friendly_filesize($i) : $i;
		}


		function get_memory_usage($userfriendly=FALSE)
		{
			$flag = version_compare(PHP_VERSION, '5.2.0', '>=') ? true : null;
			$i = memory_get_usage($flag);
			return $userfriendly ? $this->friendly_filesize($i) : $i;
		}


		function get_memory_available($userfriendly=FALSE)
		{
			$i = $this->get_memory_limit() - $this->get_memory_usage();
			return $userfriendly ? $this->friendly_filesize($i) : $i;
		}


// <<< NEW-METHODS: MEMORY-USAGE  [13.10.2011 - 13:40:57] //



} // end class hn_basic

}



if( ! class_exists('hn_path_object') )
{
	/**
	* CLASS hn_path_object
	*
	* @shortdesc takes a string, (file_in or dir_in),
	*            parses it and set properties:
	*            fullname, dirname, basename, drive - path - name - ext, exists (true|false), system (local|unc)
	*            if the string contain "../" (uplinks), it will be normalized
	* @public
	* @author Horst Nogajski, (c) 2005
	**/
	class hn_path_object extends hn_basic
	{

		/** @public read **/
		var $fullname  = null;  // 'drive' . 'path' [. 'name' [. '.ext']]
		var $dirname   = null;  // 'drive' . 'path'
		var $basename  = null;  // for type=dir: null | for type=file: 'name' [. '.ext']

		var $drive     = null;  // Drivename ( C: ) or Servername ( //REMOTENAME )
		var $path      = null;  // dirname without drive
		var $name      = null;  // for type=dir: null | for type=file: basename without extension
		var $ext       = null;  // for type=dir: null | for type=file: 'extension', (if extension exists) or ''

		var $exists    = null;  // [ TRUE    | FALSE ]
		var $system    = null;  // [ 'local' | 'unc' | 'root' ]

		var $type      = null;  // [ 'file'  | 'dir' ]


		/** @public method for file names **/
		function file_in($string)
		{
			$this->type = 'file';
			return $this->_build_object($string);
		}


		/** @public method for directory names **/
		function dir_in($string)
		{
			$this->type = 'dir';
			return $this->_build_object($string);
		}



		function file_get_part($filename,$part='fullname')
		{
			$valid = array('fullname', 'dirname', 'basename', 'drive', 'path', 'name', 'ext', 'exists', 'system');
			$part  = strtolower($part);
			if(!in_array($part,$valid))
			{
				return FALSE;
			}
			if(!$this->file_in($filename))
			{
				return FALSE;
			}
			return $this->$part;
		}


		function dir_get_part($dirname,$part='fullname')
		{
			$valid = array('fullname', 'dirname', 'basename', 'drive', 'path', 'exists', 'system');
			$part  = strtolower($part);
			if(!in_array($part,$valid))
			{
				return FALSE;
			}
			if(!$this->dir_in($dirname))
			{
				return FALSE;
			}
			return $part=='basename' ? basename($this->dirname) : $this->$part;
		}


		function get_part($part='fullname')
		{
			$valid = array('fullname', 'dirname', 'basename', 'drive', 'path', 'name', 'ext', 'exists', 'system', 'type');
			$part  = strtolower($part);
			if(!in_array($part,$valid))
			{
				return FALSE;
			}
			if($this->fullname==null)
			{
				return FALSE;
			}
			return $this->$part;
		}



		/** @private **/
		function _build_object($path)
		{
			$this->fullname  = null;
			$this->dirname   = null;
			$this->basename  = null;
			$this->drive     = null;
			$this->path      = null;
			$this->name      = null;
			$this->ext       = null;
			$this->system    = null;
			$this->exists    = null;

			$sanitized = FALSE;
			$path      = str_replace("\\","/",trim($path));
			$path      = substr($path,0,1) . str_replace('//','/', substr($path,1));
			$folder    = $this->type=='file' ? dirname($path) : $path;
			$file      = $this->type=='file' ? basename($path) : '';

			// wenn das Verzeichnis existiert, kann man eventuelle uplinks schnell loswerden
			if($this->hn_is_dir($folder))
			{
				$oldcwd = getcwd();
				chdir($folder);
				$folder = str_replace('\\', '/', getcwd());
				chdir($oldcwd);
				$sanitized = TRUE;
			}

			// drive aus folder lesen, und system ermitteln
			if(preg_match("/^Windows/",php_uname()))
			{
				if(preg_match('/[a-z|A-Z]\:/i',substr($folder,0,2)))
				{
					// Localdrive
					$newfolder    = substr($folder,2);
					$this->drive  = substr($folder,0,2);
					$this->system = 'local';
				}
				elseif(substr($folder,0,2) == '//')
				{
					// UNC-Path | Local-Network-Resource
					$newfolder    = strstr(substr($folder,2),'/');
					$newfolder    = strstr(substr($newfolder,1),'/');
					$this->drive  = str_replace($newfolder,'',$folder);
					$this->system = 'unc';
				}
				elseif(substr($folder,0,1) == '/' && substr($folder,1,1) != '/')
				{
					// Root of current Drive
					$cur_dir = getcwd();
					if(is_string($cur_dir) && preg_match('/[a-z|A-Z]\:/',substr($cur_dir,0,2)))
					{
						$this->drive = substr($cur_dir,2);
					}
					else
					{
						$this->drive = '';
					}
					$newfolder    = $folder;
					$this->system = 'root';
				}
				else
				{
					return FALSE;
				}
			}
			else // wir sind auf einem Unix-System
			{
				if(substr($folder,0,2) == '//')
				{
					// UNC-Path | Local-Network-Resource
					$newfolder    = strstr(substr($folder,2),'/');
					$newfolder    = strstr(substr($newfolder,1),'/');
					$this->drive  = str_replace($newfolder,'',$folder);
					$this->system = 'unc';
				}
				elseif(substr($folder,0,1) == '/' && substr($folder,1,1) != '/')
				{
					// Root of current Drive
					$newfolder    = $folder;
					$this->drive  = '';
					$this->system = 'root';
				}
				else
				{
					return FALSE;
				}
			}

			// folder ist jetzt der pfad ohne laufwerk- oder servername
			$folder       = $newfolder;
			unset($newfolder);
			$PathSegments = explode("/",$folder);
			if(!is_array($PathSegments))
			{
				return FALSE;
			}


			// wenn noch nicht sanitized wurde, (weil das Verzeichnis nicht existiert), wirds jetzt hier zu Fuß gemacht
			if(!$sanitized)
			{
				$uplinks = 0;
				foreach($PathSegments as $pseg)
				{
					$uplinks += $pseg=='..' ? 1 : 0;
				}

				$this->path = '';
				if($uplinks > 0)
				{
					if($PathSegments[0]=='..')
					{
						return FALSE;
					}
					if($uplinks * 2 > count($PathSegments))
					{
						return FALSE;
					}

					$k = count($PathSegments) - 1;
					while($k>=0)
					{
						if($k<0)
						{
							// ERROR
							return FALSE;
						}
						$ups = 0;
						$finished = FALSE;
						while(!$finished)
						{
							if($PathSegments[$k-$ups]=='..')
							{
								$ups++;
							}
							else
							{
								$finished = TRUE;
							}
						}
						$k -= (2 * $ups);
						if($k>-1)
						{
							$this->path .= trim($PathSegments[$k])!='' ? '/'.trim($PathSegments[$k]) : '';
						}
						$k--;
					}
				}
				else
				{
					$this->path = trim(implode('/',$PathSegments));
				}
			}
			else
			{
				$this->path = trim(implode('/',$PathSegments));
			}

			// fehlt noch, die letzten Variablen zu beschreiben
			if($this->type=='file')
			{
				$this->basename   = $file;
				$this->ext        = $this->extension($file,TRUE);
				$this->name       = strlen($this->ext)>0 ? str_replace('.'.$this->ext,'',$file) : $file;
				$this->dirname    = $this->hn_BuildFolderStr($this->drive . $this->path);
				$this->fullname   = $this->dirname . $this->basename;
				$this->exists     = is_file($this->fullname);
			}
			else
			{
				$this->dirname    = $this->hn_BuildFolderStr($this->drive . $this->path);
				$this->fullname   = $this->dirname;
				$this->basename   = null;
				$this->name       = null;
				$this->ext        = null;
				$this->exists     = $this->hn_is_dir($this->fullname);
			}
			return TRUE;
		}

	}
} // end class hn_path_object




if( ! class_exists('xip') )
{
	/*****
	 XIP Class - Proxy Detection and IP Functions class for PHP - File Name: class.XIP.php
	 Copyright (C) 2004-2006 Volkan Küçükçakar. All Rights Reserved.
	 (Volkan Kucukcakar)
	 http://www.developera.com

	 You are requested to retain this copyright notice in order to use
	 this software.

	 This program is free software; you can redistribute it and/or
	 modify it under the terms of the GNU General Public License
	 as published by the Free Software Foundation; either version 2
	 of the License, or (at your option) any later version.

	 This program is distributed in the hope that it will be useful,
	 but WITHOUT ANY WARRANTY; without even the implied warranty of
	 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	 GNU General Public License for more details.

	 You should have received a copy of the GNU General Public License
	 along with this program; if not, write to the  Free Software
	 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
	******/

	/*** Please do not remove this information text and comments ***
	*
	*   Copyright (C) 2004-2006 Volkan Küçükçakar.
	*   (Volkan Kucukcakar)
	*
	* Name          : XIP Class
	* Version       : 0.2.24 beta
	* Date          : 2006.03.12
	* File          : class.XIP.php
	* Author        : Volkan Küçükçakar
	*                (Volkan Kucukcakar)
	* EMail         : volkank@developera.com
	* Home Page     : http://www.developera.com
	* Description   : XIP Class
	*
	*           ***** Proxy Detection and IP Functions class for PHP
	*
	*                 Features:
	*
	*                 -Very easy to integrate and use
	*                 -Enhanced smart "Proxy" detection using header analysis
	*                 -Enhanced smart "Client IP" detection
	*                 -Add on IP Functions:
	*                    -Function to IP Validation
	*                    -Function to IP Public/Private(local) Check
	*                    -Function to Check if IP belongs to a network or not
	*                    -Can be used to Check if IP belongs to a "blacklisted/whitelisted" network or not
	*                 -Expandable proxy detection structure by using arrays and regular expressions
	*                 -Detects Proxy information by looking for all known and unknown (suspicious) headers which can contain any proxy evidence.
	*                 -Looks for more than 40 standard and non-standard headers
	*                 -Guess for unknown  headers using regular expressions
	*                 -Also examines remote host name...
	*
	*
	* History
	* =======
	*
	* v0.1.0 (2004)           : Foundation. "Proxy","Client IP","Proxy Type" detection.
	* v0.2.0 (2005)           : -Enhanced smart Header analysis techniques
	*                           -Expandable structure by using arrays
	*                           -Regular expression compatible
	*                           -IP Validation Function added
	*                           -Added function to check if IP is local
	*                           -Invalid and Local IP adresses are ignored if reported as client ip
	* v0.2.1 (2005)           : -search REMOTE_HOST (for words "proxy", "cache")
	* v0.2.23(2006.02.16)     : First and public release
	*                           -Well commented
	* v0.2.24(2006.03.12)     : -Fixed some notice level error reporting
	*                           -Fixed Normal Private IP List
	*                           -Added some comments
	*
	*
	*** Please do not remove this information text and comments ***
	*/



	  // CONSTANTS

	     //INTERNAL USE - Magic Number base for constants
	     $MNbase = 0;

	     //INTERNAL USE - constants for "HeaderType" array value
	     define("XIP_HT_None",++$MNbase,true); //Header value contains no readable information about proxy or client
	     define("XIP_HT_PName",++$MNbase,true); //Header value contains proxy name/version
	     define("XIP_HT_PInfo",++$MNbase,true); //Header value contains other info about proxy (reserved)
	     define("XIP_HT_ClientIP",++$MNbase,true); //Header value contains client IP


	class xip
	{

	  // VARIABLES


	     /**
	     *   Info about IP, Automatically filled for user on object creation
	     *        ['proxy'] =  string, Proxy IP (just ordinary REMOTE_ADDR value)
	     *        ['client'] = string, Real Client IP; will return Proxy IP on Anonymous proxies
	     *        ['all'] =  string, All IP addressed seperated by comma (REMOTE_ADDR+other reported addresses)
	     */
	     var $IP = array();


	     /**
	     *   Info about Proxy, Automatically filled for user on object creation
	     *        ['detected'] = boolean, true if proxy is detected
	     *        ['suspicious'] = boolean, true if proxy detection is suspicious
	     *        ['name'] = string, proxy server name
	     *        ['info'] = array, other info
	     *        ['headers'] = array, raw data and headers containing proxy evidence
	     */
	     var $Proxy = array();




	     /**
	     *   Normal Private IP List, to detect if IP is local (and to ignore if reported by proxy)
	     *   This is a simple and fixed list, I have compiled according to RFC 3330 (and some other resource)
	     *   http://www.rfc-archive.org/getrfc.php?rfc=3330
	     */
	     var $Private_IP_Normal=  '0.0.0.0/8, 1.0.0.0/8, 2.0.0.0/8, 10.0.0.0/8,
	                              127.0.0.0/8, 169.254.0.0/16, 172.16.0.0/12, 192.0.2.0/24,
	                              192.168.0.0/16, 224.0.0.0/3';
	                           /*
	                           0.0.0.0/8, "This" Network
	                           10.0.0.0/8, Private-Use Networks
	                           127.0.0.0/8, Loopback
	                           169.254.0.0/16, Link Local
	                           172.16.0.0/12, Private-Use Networks
	                           192.0.2.0/24, Test-Net
	                           192.168.0.0/16, Private-Use Networks
	                           */

	     /**
	     *   Extended Private IP List, to detect if IP is local (and to ignore if reported by proxy)
	     *   This is a more extended list, which can change in time according to reservations / allocations by IANA
	     *   Last updated 2006.01.05
	     *   http://www.cymru.com/Documents/bogon-list.html - Bogon List 3.1 - 05 JAN 2006
	     *   BTW you can always download the latest version of list into a file from http://www.cymru.com/Documents/bogon-bn-nonagg.txt
	     *   and specify the local file name to variable $exfile below.
	     *   Thanks to "Team Cymru Web Site"
	     */
	     var $Private_IP_Extended=   '0.0.0.0/8, 1.0.0.0/8, 2.0.0.0/8, 5.0.0.0/8,
	                                 7.0.0.0/8, 10.0.0.0/8, 23.0.0.0/8, 27.0.0.0/8,
	                                 31.0.0.0/8, 36.0.0.0/8, 37.0.0.0/8, 39.0.0.0/8,
	                                 42.0.0.0/8, 49.0.0.0/8, 50.0.0.0/8, 77.0.0.0/8,
	                                 78.0.0.0/8, 79.0.0.0/8, 92.0.0.0/8, 93.0.0.0/8,
	                                 94.0.0.0/8, 95.0.0.0/8, 96.0.0.0/8, 97.0.0.0/8,
	                                 98.0.0.0/8, 99.0.0.0/8, 100.0.0.0/8, 101.0.0.0/8,
	                                 102.0.0.0/8, 103.0.0.0/8, 104.0.0.0/8, 105.0.0.0/8,
	                                 106.0.0.0/8, 107.0.0.0/8, 108.0.0.0/8, 109.0.0.0/8,
	                                 110.0.0.0/8, 111.0.0.0/8, 112.0.0.0/8, 113.0.0.0/8,
	                                 114.0.0.0/8, 115.0.0.0/8, 116.0.0.0/8, 117.0.0.0/8,
	                                 118.0.0.0/8, 119.0.0.0/8, 120.0.0.0/8, 127.0.0.0/8,
	                                 169.254.0.0/16, 172.16.0.0/12, 173.0.0.0/8, 174.0.0.0/8,
	                                 175.0.0.0/8, 176.0.0.0/8, 177.0.0.0/8, 178.0.0.0/8,
	                                 179.0.0.0/8, 180.0.0.0/8, 181.0.0.0/8, 182.0.0.0/8,
	                                 183.0.0.0/8, 184.0.0.0/8, 185.0.0.0/8, 186.0.0.0/8,
	                                 187.0.0.0/8, 192.0.2.0/24, 192.168.0.0/16, 197.0.0.0/8,
	                                 198.18.0.0/15, 223.0.0.0/8, 224.0.0.0/3
	                                 ';
	     var $exfile=''; //Load Extended Private IP Address List from this file (will overwrite existing list)

	     var $ex_private=false; //Use Extended List in private IP detection
	     var $ex_proxy=false; //Use Extended List in proxy (client IP) detection


	     /**
	     *   INTERNAL USE - Proxy Evidence Headers
	     *   $Proxy_Evidence array is an EXPANDABLE STRUCTURE,
	     *   which is EVALUATED on headers to make decisions
	     *   on proxy certainty, client IP, proxy name, other info
	     *
	     *   These decision headers are made according to my small research about proxy
	     *   behaviors, proxies do not always behave like RFC's say, so there can be a big
	     *   disorder of standard and non standard headers and behaviors.
	     */
	     var $Proxy_Evidence=array(
	         /**
	         *   [0]=string HeaderName,[1]=constant HeaderType,[2]=boolean ProxyCertainty, ['value']=string Value
	         *
	         *   HeaderName    : Header name as string or regular expression
	         *   HeaderType    : What kind of info can header contain ?
	         *                   (see CONSTANTS section above for explanations...)
	         *   ProxyCertainty: Is proxy certainly present if header found?
	         *   Value         : Optional parameter must be regular expression,
	         *                   Header is only accepted if matches value (regular expression)
	         *
	         *   Note that headers are written importance ordered, first written header is evaluated first
	         */
	                    Array('HTTP_VIA', XIP_HT_PName, true), // example.com:3128 (Squid/2.4.STABLE6)
	                    Array('HTTP_PROXY_CONNECTION', XIP_HT_None, true), //Keep-Alive
	                    Array('HTTP_XROXY_CONNECTION', XIP_HT_None, true), //Keep-Alive
	                    Array('HTTP_X_FORWARDED_FOR', XIP_HT_ClientIP, true), //X.X.X.X, X.X.X.X
	                    Array('HTTP_X_FORWARDED', XIP_HT_PInfo, true), //?
	                    Array('HTTP_FORWARDED_FOR', XIP_HT_ClientIP, true), //?
	                    Array('HTTP_FORWARDED', XIP_HT_PInfo, true), //by http://proxy.example.com:8080 (Netscape-Proxy/3.5)
	                    Array('HTTP_X_COMING_FROM', XIP_HT_ClientIP, true), //?
	                    Array('HTTP_COMING_FROM', XIP_HT_ClientIP, true),
	                    /*
	                    HTTP_CLIENT_IP can be sometimes wrong (maybe if proxy chains used)
	                    First look at HTTP_X_FORWARDED_FOR if exists (it can contain multiple IP addresses comma seperated)
	                    (This is why HTTP_CLIENT_IP is written after HTTP_X_FORWARDED_FOR)
	                    */
	                    Array('HTTP_CLIENT_IP', XIP_HT_ClientIP, true), //X.X.X.X
	                    Array('HTTP_PC_REMOTE_ADDR', XIP_HT_ClientIP, true), //X.X.X.X
	                    Array('HTTP_CLIENTADDRESS', XIP_HT_ClientIP, true),
	                    Array('HTTP_CLIENT_ADDRESS', XIP_HT_ClientIP, true),
	                    Array('HTTP_SP_HOST', XIP_HT_ClientIP, true),
	                    Array('HTTP_SP_CLIENT', XIP_HT_ClientIP, true),
	                    Array('HTTP_X_ORIGINAL_HOST', XIP_HT_ClientIP, true),
	                    Array('HTTP_X_ORIGINAL_REMOTE_ADDR', XIP_HT_ClientIP, true),
	                    Array('HTTP_X_ORIG_CLIENT', XIP_HT_ClientIP, true),
	                    Array('HTTP_X_CISCO_BBSM_CLIENTIP', XIP_HT_ClientIP, true),
	                    Array('HTTP_X_AZC_REMOTE_ADDR', XIP_HT_ClientIP, true),
	                    Array('HTTP_10_0_0_0', XIP_HT_ClientIP, true),
	                    Array('HTTP_PROXY_AGENT', XIP_HT_PName, true),
	                    Array('HTTP_X_SINA_PROXYUSER', XIP_HT_ClientIP, true),
	                    Array('HTTP_XXX_REAL_IP', XIP_HT_ClientIP, true),
	                    array('HTTP_X_REMOTE_ADDR', XIP_HT_ClientIP, true),
	                    array('HTTP_RLNCLIENTIPADDR', XIP_HT_ClientIP, true),
	                    array('HTTP_REMOTE_HOST_WP', XIP_HT_ClientIP, true),
	                    array('HTTP_X_HTX_AGENT', XIP_HT_PName, true),
	                    array('HTTP_XONNECTION', XIP_HT_None, true),
	                    array('HTTP_X_LOCKING', XIP_HT_None, true),
	                    array('HTTP_PROXY_AUTHORIZATION', XIP_HT_None, true),
	                    array('HTTP_MAX_FORWARDS', XIP_HT_None, true),
	                    //array('HTTP_FROM', XIP_HT_ClientIP, true,'value'=>'/(\d{1,3}\.){3}\d{1,3}/'), //proxy is detected if header contains IP
	                    array('HTTP_X_IWPROXY_NESTING', XIP_HT_None, true),
	                    array('HTTP_X_TEAMSITE_PREREMAP', XIP_HT_None, true), //http://www.example.com/example...
	                    array('HTTP_X_SERIAL_NUMBER', XIP_HT_None, true),
	                    array('HTTP_CACHE_INFO', XIP_HT_None, true),
	                    array('HTTP_X_BLUECOAT_VIA', XIP_HT_PName, true),
	                    //search inside REMOTE_HOST
	                    /*
	                    REMOTE_HOST can always be empty whether or not you have a host name,
	                    This is because hostname lookups are turned off by default in many web hosting setups
	                    look at here for more info and solutions => http://www.php.net/manual/en/function.gethostbyaddr.php
	                    */
	                    //Yes, if remote host contains something like proxy123.example.com
	                    array('REMOTE_HOST', XIP_HT_None, true, 'value'=>'/proxy.*\..*\..*/'),
	                    //Yes, if remote host contains something like cache123.example.com
	                    array('REMOTE_HOST', XIP_HT_None, true, 'value'=>'/cache.*\..*\..*/'),
	                    //Guess Unknown headers using Regular expressions
	                    //array('/^HTTP_X_.*/', XIP_HT_None, true),
	                    array('/^HTTP_X_.*/', XIP_HT_ClientIP, true),
	                    array('/^HTTP_PROXY.*/', XIP_HT_None, true),
	                    array('/^HTTP_XROXY.*/', XIP_HT_None, true),
	                    array('/^HTTP_XPROXY.*/', XIP_HT_None, true),
	                    array('/^HTTP_VIA.*/', XIP_HT_None, false),
	                    array('/^HTTP_XXX.*/', XIP_HT_None, false),
	                    array('/^HTTP_XCACHE.*/', XIP_HT_None, false)
	                    );
	     /**
	     *   HINT ! :
	     *   If a HTTP Request Header sent as "tesT-someTHinG_aNYthing: hELLo",
	     *   PHP will set $_SERVER['HTTP_TEST_SOMETHING_ANYTHING']=hELLo
	     *   (As in PHP 4.3x installed as CGI module Apache)
	     */

	     //INTERNAL USE - IP pattern
	     var $ipp='';


	  // USER FUNCTIONS (PUBLIC)


	     /**
	     *   Function to check if IP is a Public IP or not.
	     *
	     *   boolean isPublic(string ip)
	     *   Returns true if IP is a Public IP and false if IP is not a Public IP (and false if IP is not valid)
	     *   YES, IT IS INVERSE OF $XIP->isPrivate() FUNCTION BELOW
	     */
	     function isPublic($ip)
		 {
	          return !$this->isPrivate($ip);
	     }


	     /**
	     *   Function to check if IP is a Private IP (belongs to a Local Network) or not.
	     *
	     *   boolean isPrivate(string ip)
	     *   Returns true if IP is a Private IP and false if IP is not a Private IP (and false if IP is not valid)
	     *   Example: if ($XIP->isPrivate('172.25.66.7')) echo "ip belongs to local netwok"; //will output "ip belongs to local netwok"
	     */
	     function isPrivate($ip)
		 {
	          if (!$this->isValid($ip)) return false;
	          $networks=($this->ex_private) ? $this->Private_IP_Extended : $this->Private_IP_Normal;
	          return $this->NetCheck($ip,$networks);
	     }


	     /**
	     *   Function to check if IP is Valid. (IPv4)
	     *
	     *   boolean isValid(string ip)
	     *   Returns true if IP is valid and false if IP is not valid
	     *   Does a syntax and range check on IP.
	     *   Example: if ($XIP->isValid('127.0.0.1')) echo "ip is valid"; //will output "ip is valid"
	     */
	     function isValid($ip)
		 {
	          return (preg_match('/^'.$this->ipp.'$/', trim($ip))>0);
	     }

	     /**
	     *   Function to check if IP belongs to given Network or not
	     *   This function can be used to Check if IP belongs to a "blacklisted/whitelisted" network as well
	     *
	     *   boolean NetCheck(string ip, mixed network)
	     *   $networks parameter should be a string, comma separated strings,one string per line or array of strings in this format "IP[/MASK]", which you prefer...
	     *   Example: if ($XIP->NetCheck('127.0.0.1','127.0.0.0/8')) echo "ip belongs to given network"; //will output "ip belongs to given network"
	     *            if ($XIP->NetCheck('127.0.0.1','127.0.0.0/255.0.0.0')) echo "ip belongs to given network"; //will output "ip belongs to given network"
	     *
	     *            //If you want to check if IP is in range of 192.168.2.0 to 192.168.2.255
	     *            if ($XIP->NetCheck($ip,'192.168.2.0/255.255.255.0')) echo "YES IN RANGE";
	     *            //If you want to check if IP is in range of 192.168.0.0 to 192.168.255.255
	     *            if ($XIP->NetCheck($ip,'192.168.2.0/255.255.0.0')) echo "YES IN RANGE";
	     */
	     function NetCheck($ip,$networks='')
		 {
	          //use Default netwok IP addresses if $networks parameter omitted
	          if (empty($networks)) $networks=$this->Network_IP;
	          if (!$this->isValid($ip)) return false;
	          $ipl = ip2long(trim($ip));
	          //if ($ipl===false) $ipl=-1;//ip2long returns FALSE for 255.255.255.255, convert it to its real value -1 (only PHP 5.0.0 < 5.0.3)
	          $ips=(is_array($networks)) ? $networks : preg_split('/[\s,]+/',$networks);
	          foreach ($ips as $value){
	               if (preg_match('/^'.$this->ipp.'(\/\d|\/'.$this->ipp.')?/',$value)){
	                    $ipa = explode('/', $value);
	                    if (count($ipa)<2) $ipa[1]='';//prevent notice level error (I've added this line for some users reported notice level errors)
	                    $net = ip2long($ipa[0]);
	                    //if ($net===false) $net=-1;
	                    $x = ip2long($ipa[1]);
	                    //if ($x===false) $x=-1;
	                    $mask =  long2ip($x) == $ipa[1] ? $x : 0xffffffff << (32 - $ipa[1]);
	                    if (($ipl & $mask) == ($net & $mask)) return true;
	               }
	          }
	          return false;
	     }



	  // INTERNAL FUNCTIONS (PRIVATE)

	    /**
	    *   This constructor function will be run automatically on object creation
	    *   Fills all useful information to $XIP->IP[] and $XIP->Proxy[] arrays for user
	    *   No need to call any functions later for IP detection or proxy detection (see example files)
	    *   However you can call some IP fuctions if you need
	    */
		function XIP()
		{
			$remote_adress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
			//INTERNAL USE - Some temporary variables used internally, do not touch...
			$ips = '';
			$ipa = array($remote_adress);

			$this->Proxy['detected'] = false;
			$this->Proxy['suspicious'] = false;
			$this->Proxy['name'] = NULL;
			$this->Proxy['info'] = array();
			$this->Proxy['headers'] = array();

			//INTERNAL USE - IP octet pattern
			$ipo='(25[0-5]|2[0-4]\d|[01]?\d\d|\d)';
			//INTERNAL USE - IP pattern
			$this->ipp="$ipo\\.$ipo\\.$ipo\\.$ipo";

			//If filename specified, Load Extended Private IP Address List from this file
			if (!empty($this->exfile)){
			$tmp=implode('', file($this->exfile));
				if ((!$tmp===false)&&(preg_match('/'.$this->ipp.'/', $tmp))) $this->Private_IP_Extended=$tmp;
			}

		  /**
			*   EVALUATE $Proxy_Evidence array on HEADERS !!!
			*/
			foreach ($this->Proxy_Evidence as $value)
			{
				$tmp=$this->FindHeaders($value[0]);
				foreach ($tmp as $hkey => $hvalue)
				{
	                //make decision on proxy certainty data
	                $pkey= ($value[2]===true) ? 'detected' : 'suspicious';
	                if (array_key_exists('value',$value)){
	                     if (preg_match($value['value'],$hvalue)) $this->Proxy[$pkey]=true;
	                }else{
	                     $this->Proxy[$pkey]=true;
	                }
	                //collect data about client IP, proxy name, other info
	                if ($value[1]==XIP_HT_PName && empty($this->Proxy['name'])) $this->Proxy['name']=$hvalue;
	                if ($value[1]==XIP_HT_ClientIP) $ips.=$hvalue.',';//ips will be parsed later; also headers can contain multiple IP addresses
	                if (($value[1]==XIP_HT_PInfo)||($value[1]==XIP_HT_PName)) $this->Proxy['info'][$hkey]=$hvalue;
	                $this->Proxy['headers'][$hkey]=$hvalue;
	           }
			}
			//both 'detected' and 'suspicious' cannot be true
			if ($this->Proxy['detected'])
			{
				$this->Proxy['suspicious']=false;
			}

			//Fill $XIP->IP['proxy'] with REMOTE_ADDR always
			$this->IP['proxy']  = $remote_adress;
			$this->IP['client'] = ''; //multiple call safe (with different options if any)
			//make decision on client IP
			if(preg_match_all('/'.$this->ipp.'/',$ips,$match))
			{
				foreach($match[0] as $value)
				{
					if(!in_array($value,$ipa))
					{
						$ipa[]=$value;
					}
					//Set the first public IP found as client IP
					//if ($this->isPublic($value) && empty($this->IP['client'])) $this->IP['client']=$value;
					$network=($this->ex_proxy) ? $this->Private_IP_Extended : $this->Private_IP_Normal;
					if(!$this->NetCheck($value,$network) && empty($this->IP['client']))
					{
						$this->IP['client']=$value;
					}
				}
			}
			//Fill $XIP->IP['all'] with REMOTE_ADDR and all IP addresses comma separated
			$this->IP['all']=implode(",", $ipa);
			//Fill $XIP->IP['client'] with REMOTE_ADDR if client IP or proxy not detected
			if (empty($this->IP['client'])) $this->IP['client']=$this->IP['proxy'];
			$this->Proxy['anonymous']=($this->IP['client']==$this->IP['proxy']) ? true: false;
		}


	     /**
	     *   INTERNAL USE - Regular Expression compatible Find Headers function
	     *   To Query existence of a request header and returns found headers
	     */
	     function FindHeaders($name)
		 {
	          $result=array();
	          //if ($name[0]<>'/') $name='/^'.$name.'$/';//If reg.ex. not found convert it to an "exact phrase" reg.ex. as default
	          //Return header directly if not written as regular expression (separated for speed)
	          if ($name[0]<>'/') {
	               if ( array_key_exists($name,$_SERVER) ) $result[$name]=$_SERVER[$name];
	          }else{
	               //Regular expression headers search
	               foreach ($_SERVER as $key => $value){
	                    if ( preg_match($name,$key,$match) ) $result[$key]=$value;
	               }
	          }
	          return $result;
	     }

	}
} // end class XIP

if( ! function_exists('hn_get_xip') )
{
	function hn_get_xip( $html=false )
	{
		$xip = new xip();
		if( $xip->IP['client'] === $xip->IP['proxy'] )
		{
			# es wurde kein Proxy benutzt / erkannt
			$res = 'IP-client: ' . $xip->IP['client'] . ' ('. ( $xip->IP['client']==null ? 'NULL' : gethostbyaddr($xip->IP['client']) ) .')';
		}
		else
		{
			$res = 'IP-client: ' . $xip->IP['client'] . ' ('. ( $xip->IP['client']==null ? 'NULL' : gethostbyaddr($xip->IP['client']) ) .') | IP-proxy: ' . $xip->IP['proxy'] . ' ('. ( $xip->IP['proxy']==null ? 'NULL' : gethostbyaddr($xip->IP['proxy']) ) .') | '."\n - all (" . $xip->IP['all'] . ") - ";
		}
		unset($xip);
		return $html ? str_replace("\n","<br>\n",$res) : $res;
	}
}



