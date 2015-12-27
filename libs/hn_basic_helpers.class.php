<?php
/*****************************************************************************
*  PHP-CLASSES  -   hn_php_api
*                   hn_memory_usage
*                   hn_php_tempfile
*                   hn_php4_destructor
*
*  @php_version -   4.2.x
* ---------------------------------------------------------------------------
*  @version     -   v1.5
*  @date        -   $Date: 2014/05/11 10:49:06 $
*  @author      -   Horst Nogajski <coding AT nogajski DOT de>
*  @licence     -   GNU LGPL - http://www.gnu.org/licenses/lgpl.html
* ---------------------------------------------------------------------------
*  $Source: /PHP-CLASSES/hn_basic_helpers/hn_basic_helpers.class.php,v $
*  $Id: hn_basic_helpers.class.php,v 1.3 2014/05/11 10:49:06 horst Exp $
******************************************************************************
  *
  * LAST CHANGES:
  *
  * 2012-12-02   new     class       hn_php_api( $check_MIN_Version=null )
  *
  * 2013-01-09   new     class       hn_memory_usage()  -  methods:  usage  |  limit  |  available
  *
  * 2013-01-19   new     class       hn_php_tempfile()     Wrapperclass for writing to php://temp/maxmemory  with workaround for PHP < 5.1.0
  *
  * 2013-01-20   new     class       hn_php4_destructor()  -  separated from hn_basic()
  *              change  hn_basic    now extends class hn_php4_destructor
  *
  * 2013-01-23   change  hn_memory_usage extends to work with PHP 4 and/or any PHP-version that is compiled without "--enable-memory-limit"
  *
  * 2014-05-11   new     class       hn_timer()  supersedes the class timer()
  *                                  methods: start($name), stop($name), pause($name), resume($name), current($name)
  *
**/



if( ! class_exists('hn_php_api') )
{
    class hn_php_api
    {
        /** @public readOnly **/
        var $ver         = null;   // string  PHP Version
        var $min         = null;   // bool    true | false  - if checked a Versionnumber
        var $ver_min     = null;   // string  the minimum required Versionnumber

        var $api         = null;   // string  full api info
        var $sys         = null;   // string  full sys info
        var $TS          = null;   // bool    ThreadSafe ?
        var $mem         = null;   // bool    PHP compiled with "--enable-memory-limit" ( is default for 5.2.1 and greater )

        var $cli         = null;   // bool    running as Command Line Interface
        var $cgi         = null;   // bool    running as cgi or fast-cgi Version
        var $apache      = null;   // bool    running as Apache-Modul

        function hn_php_api( $check_MIN_Version=null )
        {
            ob_start();
            phpinfo(INFO_GENERAL);
            $buffer = str_replace("\r\n", "\n", ob_get_contents());
            ob_end_clean();

            $this->ver = phpversion();
            $this->ver_min = is_string($check_MIN_Version) ? $check_MIN_Version : null;
            $this->min = ! is_null($this->ver_min) ? (version_compare($this->ver_min, $this->ver, '>=') ? false : true) : null;

            $pattern = preg_match('#</td>#msi', $buffer)===1 ? '#>Server API.*?</td><td.*?>(.*?)</td>#msi' : '#\nServer API.*?=>(.*?)\n#msi';
            $this->api = preg_match($pattern, $buffer, $matches)===1 ? trim($matches[1]) : null;

            $pattern = preg_match('#</td>#msi', $buffer)===1 ? '#>System.*?</td><td.*?>(.*?)</td>#msi' : '#\nSystem.*?=>(.*?)\n#msi';
            $this->sys = preg_match($pattern, $buffer, $matches)===1 ? trim($matches[1]) : null;

            $this->apache = preg_match('/apache/msi',$this->api)===1 ? true : false;
            $this->cgi = preg_match('/cgi/msi',$this->api)===1 ? true : false;
            $this->cli = $this->api=='Command Line Interface' ? true : false;

            # ThreadSafe Version ?
            $pattern = preg_match('#</td>#msi', $buffer)===1 ? '#>Thread Safety.*?</td><td.*?>(.*?)</td>#msi' : '#\nThread Safety.*?=>(.*?)\n#msi';
            $this->TS = preg_match($pattern, $buffer, $matches)===1 ? (strtolower(trim($matches[1]))=='enabled' ? true:false) : null;

            # compiled with enable memory limit ?
            $s = trim(ini_get('memory_limit'));
            $this->mem = function_exists('memory_get_usage') ? true : false;
        }
    }
} // end class hn_php_api




if( ! class_exists('hn_memory_usage') )
{
    class hn_memory_usage
    {
        /** @public readOnly **/
        var $native  = null;

        /** @private **/
        var $limit_i = null;
        var $limit_s = null;
        var $enabled = null;
        var $user_i  = null;


        /** constructor **/
        function hn_memory_usage()
        {
            $this->enabled = null;
            $this->native  = null;
            $this->limit_i = null;
            $this->limit_s = null;
            $this->user_i  = null;
            $this->limit();
        }



        /** @public  is usefull when PHP is compiled without "--enable-memory-limit" **/
        function set_user_limit( $sShortNotation_or_iBytes )
        {
            $i = $this->_unshort( $sShortNotation_or_iBytes );
            if( $this->enabled && $i>$this->limit_i )
            {
                return false;
            }
            if( ! $this->enabled )
            {
                $this->enabled = true;
            }
            $this->user_i  = $i;
            $this->limit_i = $i;
            $this->limit_s = $this->_short_notation( $i );
            return true;
        }



        /** @public **/
        function limit( $short_notation=false, $refresh=false )
        {
            if( $this->limit_i!==null && $refresh!==true )
            {
                return $short_notation ? $this->limit_s : $this->limit_i;
            }

            # Memory-Limit
            $s = trim(ini_get('memory_limit'));
            if( $s=='' && ! function_exists('memory_get_usage') )
            {
                # PHP is compiled without "--enable-memory-limit"
                $limit = -1;
            }
            elseif( $s=='' && function_exists('memory_get_usage') )
            {
                # ??? should be impossible !!!
                $limit = -1;
            }
            else
            {
                $limit = $s;
            }
            $i = $this->_unshort( $limit );

            $this->enabled = $i===-1 ? false : true;
            $this->native  = $i===-1 ? false : true;
            $this->limit_s = $i===-1 ? '-1'  : $this->_short_notation( $i );
            $this->limit_i = $i;
            if( ! is_null($this->user_i) )
            {
                $this->set_user_limit( $this->user_i );
            }
            return $short_notation ? $this->limit_i : $this->limit_s;
        }



        /** @public **/
        function usage( $short_notation=false )
        {
            $i = $this->_hn_memory_get_usage();
            return $short_notation ? $this->_short_notation($i) : $i;
        }



        /** @public **/
        function available( $short_notation=false )
        {
            if( ! $this->enabled )
            {
                  return $short_notation ? 'unknown' : -1;
            }
            $i = $this->limit() - $this->usage();
            return $short_notation ? $this->_short_notation($i) : $i;
        }





        /** @private **/
        function _short_notation( $size )
        {
            $unit = array('',' B',' KB',' MB',' GB',' TB');
            $j = 0;
            while($size >= pow(1024,$j))
            {
                $j++;
            }
            $size = round($size / pow(1024,$j-1) * 100) / 100 . $unit[$j];
            return str_replace('.', ',', $size);
        }


        /** @private **/
        function _unshort( $size )
        {
            # der Wert als String
            $size = strval($size);
            preg_match('/^[0-9].*([k|K|m|M|g|G])$/',$size,$match);
            $char = isset($match[1]) ? $match[1] : '';
            switch(strtoupper($char))
            {
                case 'G':
                    $i = intval(str_replace(array('G','g'),'',$size)) * 1073741824;
                    break;
                case 'M':
                    $i = intval(str_replace(array('M','m'),'',$size)) * 1048576;
                    break;
                case 'K':
                    $i = intval(str_replace(array('K','k'),'',$size)) * 1024;
                    break;
                default:
                    $i = intval($size);
            }
            return $i;
        }


        /** @private **/
        function _hn_memory_get_usage()
        {
            if( function_exists('memory_get_usage') )
            {
                $i = version_compare(phpversion(), '5.2.0', '>=') ? memory_get_usage(true) : memory_get_usage();
                return $i;
            }

            # This part was taken from the php manual user comments on php.net <e dot a dot schultz at gmail dot com>
            # If its Windows
            # Tested on Win XP Pro SP2. Should work on Win 2003 Server too
            # Works on Win 7 too
            if( substr( PHP_OS, 0, 3 ) == 'WIN' )
            {
                $output = array();
                exec( 'tasklist /FI "PID eq ' . getmypid() . '" /FO LIST', $output );
                return intval( preg_replace( '/[\D]/', '', $output[5] ) * 1024 );
            }
            else
            {
                //We now assume the OS is UNIX
                //Tested on Mac OS X 10.4.6 and Linux Red Hat Enterprise 4
                //This should work on most UNIX systems
                $pid = getmypid();
                exec("ps -eo%mem,rss,pid | grep $pid", $output);
                $output = explode("  ", $output[0]);
                //rss is given in 1024 byte units
                return intval( $output[1] * 1024 );
            }
        }

    }
} // end helper class hn_memory_usage




if( ! class_exists('hn_php4_destructor') )
{
    class hn_php4_destructor
    {

        /** @shortdesc Register our class-method 'hn_basic_Destructor()' as shutdownfunction!
          **/
        function hn_php4_destructor()
        {
            if( ! isset($GLOBALS['__hn_ShutdownFunction__']) )
            {
                register_shutdown_function( array(&$this,'_hn_basic_Destructor') );
                $GLOBALS['__hn_ShutdownFunction__']['enabled'] = true;
            }
        }


        /** @shortdesc This add a func-call to Globalarray which will executed when Destructor runs!
          * @public
          **/
        function runOnShutdown( $func='' )
        {
            if( $func != '' )
            {
                $GLOBALS['__hn_ShutdownFunction__']['__THINX2UNSET__'][md5($func.strval(time()))] = addslashes($func);
            }
        }


        /** @shortdesc This will be registered as ShutdownFunc. It closes the Logfilehandle, and execute all code which was added by the runOnShutdown()-func!
          * @private
          **/
        function _hn_basic_Destructor()
        {
            # should run only once!
            if( ! isset($GLOBALS['__hn_ShutdownFunction__']['enabled']) || $GLOBALS['__hn_ShutdownFunction__']['enabled']!==true )
            {
                return;
            }
            unset($GLOBALS['__hn_ShutdownFunction__']['enabled']);
            if( isset($GLOBALS['__hn_ShutdownFunction__']['__THINX2UNSET__']) && is_array($GLOBALS['__hn_ShutdownFunction__']['__THINX2UNSET__']) )
            {
                foreach( $GLOBALS['__hn_ShutdownFunction__']['__THINX2UNSET__'] as $func )
                {
                    $func = stripslashes($func);
                    eval("$func");
                }
            }
        }

    }
} // end class hn_php4_destructor




if( ! class_exists('filo') )
{
    /** @shortdesc: Stack, First In - Last Out  **/
    class filo
    {

        /** @private **/
        var $elements;
        /** @private **/
        var $debug;

        /** @private **/
        function filo( $debug=FALSE )
        {
            $this->debug = $debug;
            $this->zero();
        }

        /** @private **/
        function push( $elm )
        {
            array_push( $this->elements, $elm );
            if($this->debug) echo "<p>filo->push(".$elm.")</p>";
        }

        /** @private **/
        function pop()
        {
            $ret = array_pop( $this->elements );
            if($this->debug) echo "<p>filo->pop() = $ret</p>";
            return $ret;
        }

        /** @private **/
        function zero()
        {
            $this->elements = array();
            if($this->debug) echo "<p>filo->zero()</p>";
        }

    }
} // end class FILO




if( ! class_exists('timer') )
{
    /** phpTimer: A simple script to time script execution
    * Copyright (C) 2001 Roger Raymond ~ epsilon7@users.sourceforge.net
    *
    * This library is free software; you can redistribute it and/or
    * modify it under the terms of the GNU Lesser General Public
    * License as published by the Free Software Foundation; either
    * version 2.1 of the License, or (at your option) any later version.
    *
    * This library is distributed in the hope that it will be useful,
    * but WITHOUT ANY WARRANTY; without even the implied warranty of
    * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
    * Lesser General Public License for more details.
    *
    * You should have received a copy of the GNU Lesser General Public
    * License along with this library; if not, write to the Free Software
    * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
    *
    * @shortdesc little timer class
    * @private
    * @author Roger Raymond
    * @version 0.1
    * @date 2001
    **/
    class timer
    {
        /** @private **/
        var $version = '0.1';
        /** @private **/
        var $_enabled = FALSE;

        /** @private **/
        function timer()
        {
            $this->_enabled = true;
        }


        /** @public **/
        function start( $name = 'default' )
        {
            $this->timer_start( $name );
        }
        function timer_start( $name = 'default' )
        {
            if( $this->_enabled )
            {
                $this->_timing_start_times[$name] = explode(' ', microtime());
            }
        }


        /** @public **/
        function stop( $name = 'default' )
        {
            $this->timer_stop( $name );
        }
        function timer_stop( $name = 'default' )
        {
            if( $this->_enabled )
            {
                $this->_timing_stop_times[$name] = explode(' ', microtime());
            }
        }


        /** @public **/
        function current( $name = 'default' )
        {
            return $this->timer_get_current( $name );
        }
        function timer_get_current( $name = 'default' )
        {
            if( ! $this->_enabled )
            {
                return 0;
            }
            if( ! isset( $this->_timing_start_times[$name] ) )
            {
                return 0;
            }
            if( ! isset( $this->_timing_stop_times[$name] ) )
            {
                $stop_time = explode(' ', microtime());
            }
            else
            {
                $stop_time = $this->_timing_stop_times[$name];
            }
            // do the big numbers first so the small ones aren't lost
            $current = $stop_time[1] - $this->_timing_start_times[$name][1];
            $current += $stop_time[0] - $this->_timing_start_times[$name][0];
            return sprintf("%.10f",$current);
        }
    }
} // end class timer





if( ! class_exists('hn_timer')) {
    class hn_timer {

        /* @private */
        var $_startTimes = array();
        var $_stopTimes = array();
        var $_stopwatchRunning = array();
        var $_amount = array();

        /* @public */
        function hn_timer() {
        }

        /* @private */
        function _add2account($name = 'default') {
            if(!isset($this->_stopwatchRunning[$name])) return false;
            // we only add it if we are in paused or stopped mode
            if($this->_stopwatchRunning[$name]) return false;
            // do the big numbers first so the small ones aren't lost
            $current = $this->_stopTimes[$name][1] - $this->_startTimes[$name][1];
            $current += $this->_stopTimes[$name][0] - $this->_startTimes[$name][0];
            $this->_amount[$name] += floatval($current);

        }

        /* @public */
        function start($name = 'default') {
            // if we have already one with that name, deny it
            if(isset($this->_stopwatchRunning[$name])) return false;
            // create a stopwatch and start it
            $this->_amount[$name] = 0;
            $this->_stopwatchRunning[$name] = true;
            $this->_startTimes[$name] = explode(' ', microtime());
            return true;
        }

        /* @public */
        function pause($name = 'default') {
            return $this->stop($name);
        }

        /* @public */
        function resume($name = 'default') {
            if(!isset($this->_stopwatchRunning[$name])) return false;
            $this->_stopwatchRunning[$name] = true;
            $this->_startTimes[$name] = explode(' ', microtime());
            return true;
        }

        /* @public */
        function stop($name = 'default') {
            // if we do not have one with that name, deny it
            if(!isset($this->_stopwatchRunning[$name])) return false;
            // if it is not in running mode, deny it
            if(!$this->_stopwatchRunning[$name]) return false;
            // stop it
            $this->_stopTimes[$name] = explode(' ', microtime());
            $this->_stopwatchRunning[$name] = false;
            // add current amount
            $this->_add2account($name);
            // prepare it for optional further runs
            $this->_startTimes[$name] = 0;
            $this->_stopTimes[$name] = 0;
            return true;
        }

        /* @public */
        function current($name = 'default', $total=true) {
            // if we do not have one with that name, deny it
            if(!isset($this->_stopwatchRunning[$name])) return false;
            // check if is is actually running
            if($this->_stopwatchRunning[$name]) {
                // it is running, we need to fetch the current amount on the fly
                $stopTimes = explode(' ', microtime());
                $current = $stopTimes[1] - $this->_startTimes[$name][1];
                $current += $stopTimes[0] - $this->_startTimes[$name][0];
                // check if we have to add any previous recorded amount to it
                $current += true==$total ? $this->_amount[$name] : 0;
                // send the requested amount
                return sprintf("%.10f", $current);
            }
            // it is not running, but a current amount on the fly is requested!
            if(true!==$total) {
                return false;
            }
            // it is not running, we send the total recorded amount
            return sprintf("%.10f", $this->_amount[$name]);
        }

        /* @public */
        function delete($name = 'default') {
            unset($this->_amount[$name]);
            unset($this->_stopwatchRunning[$name]);
            unset($this->_startTimes[$name]);
            unset($this->_stopTimes[$name]);
        }
    }
} // end class hn_timer







if( ! class_exists('hn_SizeCalc') )
{
    /**
    * @shortdesc This class holds some methods to calculate Picture dimensions
    * @public
    * @author Horst Nogajski
    * @version 0.3
    * @date 2004-Jun-21
    **/
    class hn_SizeCalc
    {

        /** @private **/
        var $a = 0;
        /** @private **/
        var $b = 0;
        /** @private **/
        var $landscape = NULL;

        /** Mit der Methode down werden Werte (falls sie groesser sind als die MaxWerte) verkleinert.
          * Werte die kleiner sind als die MaxWerte bleiben unveraendert.
          *
          * @shortdesc Use this for downsizing only. Means: if the source is smaller then the max-sizes, it will not changed.
          * @public
          **/
        function down(&$a,&$b,$a_max,$b_max)
        {
            $this->a = 0;
            $this->b = 0;
            $this->landscape = $a >= $b ? TRUE : FALSE;
            $check = 1;
            if($a > $a_max) $check += 3;
            if($b > $b_max) $check += 2;

            switch ($check)
            {
                case 1:
                    // Bild ist kleiner als max Groesse fuer a und b
                    $this->b = ceil($b);
                    $this->a = ceil($a);
                    break;
                case 3:
                    // Seite b ist groesser als max Groesse,
                    // Bild wird unter beruecksichtigung des Seitenverhaeltnisses
                    // auf Groesse fuer b gerechnet
                    $this->b = ceil($b_max);
                    $this->a = ceil($a / ($b / $this->b));
                    break;
                case 4:
                    // Seite a ist groesser als max Groesse,
                    // Bild wird unter beruecksichtigung des Seitenverhaeltnisses
                    // auf Groesse fuer a gerechnet
                    $this->a = ceil($a_max);
                    $this->b = ceil($b / ($a / $this->a));
                    break;
                case 6:
                    // BEIDE Seiten sind groesser als max Groesse,
                    // Bild wird unter beruecksichtigung des Seitenverhaeltnisses
                    // zuerst auf Groesse fuer a gerechnet, und wenns dann noch
                    // nicht passt, nochmal fuer b. Danach passt's! ;)
                    $this->a = ceil($a_max);
                    $this->b = ceil($b / ($a / $this->a));
                    if($this->b > $b_max)
                    {
                        $this->b = ceil($b_max);
                        $this->a = ceil($a / ($b / $this->b));
                    }
                    break;
            }

            // RUECKGABE DER WERTE ERFOLGT PER REFERENCE
            $a = $this->a;
            $b = $this->b;
        }


        /** Mit der Methode up werden Werte (falls sie groesser sind als die MaxWerte) verkleinert
          * und falls sie kleiner sind als die MaxWerte werden sie vergroessert!
          *
          * @shortdesc Use this for up- and downsizing. Means: if the source is graeter then the max-sizes,
          * they become downsized, and if they are smaller then the max-sizes, they become upsized!
          * @public
          **/
        function up(&$a,&$b,$a_max,$b_max)
        {
            // falls das Bild zu gross ist wird es jetzt kleiner gerechnet, so das es max_a und max_b nicht ueberschreitet
            $this->down($a,$b,$a_max,$b_max);

            // reset
            $this->a = 0; // width
            $this->b = 0; // height

            //$this->landscape = $a >= $b ? TRUE : FALSE;

            // wenn jetzt a und b kleiner sind dann muss es vergroessert werden
            if($a < $a_max && $b < $b_max)
            {
                // ermitteln der prozentualen differenz vom Sollwert
                $pa = $this->_diffProzent($a,$a_max);
                $pb = $this->_diffProzent($b,$b_max);
                if($pa >= $pb)
                {
                    // b auf b_max setzen
                    $this->a = ceil($a * ($b_max / $b));
                    $this->b = $b_max;
                }
                else
                {
                    // a auf a_max setzen
                    $this->b = ceil($b * ($a_max / $a));
                    $this->a = $a_max;
                }
                // RUECKGABE DER WERTE ERFOLGT PER REFERENCE
                $a = $this->a;
                $b = $this->b;
                $this->down($a,$b,$a_max,$b_max);
            }
        }



        /** @public
          * conversion pixel -> millimeter in 72 dpi
          **/
        function px2mm($px)
        {
            return $px*25.4/72;
        }


        /** @private **/
        function _diffProzent($ist,$soll)
        {
            return (int)(($ist * 100) / $soll);
        }

    }
} // END CLASS hn_SizeCalc




/**
* Wrapperclass for writing to php://temp/maxmemory
*
* with workaround for PHP 4.x and 5.0.x
*/
if( ! class_exists('hn_php_tempfile') )
{
    class hn_php_tempfile
    {
        var $native  = null;
        var $maxmem  = null;
        var $tmpnam  = null;
        var $locked  = null;
        var $fp      = null;

        function hn_php_tempfile( $maxmem='2M' )
        {
            $this->maxmem = hn_memory_usage::_unshort($maxmem);
            $this->native = version_compare(phpversion(), '5.1.0', '>=') ? true : false;
            if( $this->native )
            {
                $this->fp = @fopen('php://temp/maxmemory:'.$this->maxmem, 'rb+');
            }
            else
            {
                $this->tmpnam = function_exists('sys_get_temp_dir') ? sys_get_temp_dir().'/php_'.strval(time()).'.tmp' : is_dir(getenv('TMP')) ? getenv('TMP').'/php_'.strval(time()).'.tmp' : null;
                $this->tmpnam = is_null($this->tmpnam) ? tempnam( dirname(__FILE_), 'php' ) : $this->tmpnam;
                $this->tmpnam = str_replace("\\", "/", $this->tmpnam );
                @touch($this->tmpnam);
                $this->fp = @fopen($this->tmpnam, 'rb+');
                if( ! is_resource($this->fp) )
                {
                    return;
                }
                $this->locked = flock( $this->fp, LOCK_EX );
            }
        }


        function get_pointer()
        {
            return $this->fp;
        }


        function __destruct()
        {
            if( is_resource($this->fp) )
            {
                if( $this->locked )
                {
                    flock( $this->fp, LOCK_UN );
                }
                fclose( $this->fp );
                $this->fp = null;
            }
        }

    }
} // end class hn_php_tempfile





if( ! function_exists('fnmatch') )
{
    function fnmatch($pattern, $string)
    {
        return preg_match("#^".strtr(preg_quote($pattern,'#'), array('\*'=>'.*', '\?'=>'.', '\['=>'[', '\]'=>']'))."$#i", $string);
    }
}
