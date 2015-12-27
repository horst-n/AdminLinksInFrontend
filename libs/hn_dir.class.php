<?php
/*****************************************************************************
  * @script_type -  PHP-CLASS
  * @php_version -  4.2.x
  * @version     -  2.1
  * @SOURCE-ID   -  1.57
  * -------------------------------------------------------------------------
  * @author      -  Horst Nogajski <coding@nogajski.de>
  * @copyright   -  (c) 1999 - 2012
  * @licence     -  LGPL
  * -------------------------------------------------------------------------
  * $Source: /PHP-CLI/php_includes/hn_dir.class.php,v $
  * $Id: hn_dir.class.php,v 1.58 2013/08/10 23:18:10 horst Exp $
  ****************************************************************************
  *
  * LAST CHANGES:
  *
  * 2012-01-09   new     hn_dirbasic -> DELETE_EMPTY_DIRS()
  *
  * 2013-01-28   new     hn_dirbasic -> DELETE_ALL_FILES_IN_DIR( $dirname, $recursive=false )
  *
**/


// REQUIRED: hn_basic.class.php
if(!class_exists('hn_basic'))
{
	$classfile = dirname(__FILE__).'/hn_basic.class.php';
	if(!file_exists($classfile))
	{
		die("needed IncludeFile not found: \r\n".str_replace('.class.php','',basename($classfile)));
	}
	require_once($classfile);
}




/**
  * @shortdesc Basic dir and file functions
  * @public
  * @author Horst Nogajski, Copyright (c) 2004
  * @version 1.0
  * @date 2004-Apr-10
  **/
if(!class_exists('hn_dirbasic')) {

class hn_dirbasic extends hn_basic
{

	/////////////////////////////////////////////
	//	CONSTRUCTOR

		/** @private **/
		function hn_dirbasic($config='',$secure=TRUE)
		{
			// call constructor of parent class
			$this->hn_basic();

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
					$this->msg = 'Config-Array extracted in secure-mode: $hn_dirbasic->CONSTRUCTOR() [ possible: '.count($valid).' | extracted: '.$extracted.' | skipped: '.(count($config)-$extracted).' ]';
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
					$this->msg = 'Config-Array extracted in _UN_secure-mode: $hn_dirbasic->CONSTRUCTOR() [ extracted: '.count($config).' ]';
					$this->debug('hn_basic,ini');
				}
			}
			else
			{
				$this->msg = 'Initialized without Config-Array: $hn_dirbasic->CONSTRUCTOR()';
				$this->debug('hn_basic,ini');
			}

		}



	/////////////////////////////////////////////
	//	PUBLIC METHODS



		function dir_size($dir,$friendlyStr=FALSE)
		{
			$dir = $this->hn_BuildFolderStr($dir);
			$hdl = @opendir($dir);
			if($hdl===FALSE)
			{
				return FALSE;
			}
			$size = 0;
			while($file = @readdir($hdl))
			{
				if($file==='..' || $file==='.')
				{
					continue;
				}
				if(is_file($dir.$file))
				{
					$size += filesize($dir.$file);
				}
				elseif($this->hn_is_dir($dir.$file))
				{
					$size += $this->dir_size($dir.$file,FALSE);
				}
			}
			@closedir($hdl);
			return $friendlyStr ? $this->friendly_filesize($size) : $size;
		}



		/** @shortdesc Copy a file from source to destination, also if the destination directory does not exist. Optionally it sync's the filetimes.
		  * @public
		  * @type bool
		  * @return True if the file was succesfully copied
		  **/
		function copyfile($src,$dst,$syncTime=TRUE)
		{
			if(!$this->hn_is_dir(dirname($dst)))
			{
				$this->hn_makedir(dirname($dst));
			}
			$success = FALSE;
			if(@copy($src,$dst))
			{
				$success = TRUE;
			}
			if(!$success)
			{
				$this->msg = "ERROR: Could not copy file \$hn_dirbasic->copyfile($src,$dst)\n";
				$this->debug('error,dirbasic,file,dir');
				return FALSE;
			}

			$this->msg = "Success: \$hn_dirbasic->copyfile($src,$dst)";
			$this->debug('dirbasic,file,dir');
			if($syncTime)
			{
				return @touch($dst,filemtime($src));
			}
			else
			{
				return TRUE;
			}
		}


		/** @shortdesc Copy a file from source to destination, also if the destination directory does not exist.
		  * @public
		  * @type bool
		  * @return True if the file was succesfully copied
		  **/
		function movefile($src,$dst)
		{
			$success = FALSE;
			if(@rename($src,$dst))
			{
				$success = TRUE;
			}
			elseif(!$this->hn_is_dir(dirname($dst)))
			{
				$this->hn_makedir(dirname($dst));
				$success = @rename($src,$dst);
			}
			if($success)
			{
				$this->msg = "Success: \$hn_dirbasic->movefile($src,$dst)";
				$this->debug('dirbasic,file,dir');
				return TRUE;
			}
			if(!$this->copyfile($src,$dst,true))
			{
				$this->msg = "ERROR: Could not move file \$hn_dirbasic->movefile($src,$dst)\n";
				$this->debug('error,dirbasic,file,dir');
				return FALSE;
			}
			if(@unlink($src))
			{
				$this->msg = "Success: \$hn_dirbasic->movefile($src,$dst)";
				$this->debug('dirbasic,file,dir');
				return TRUE;
			}
			else
			{
				$this->msg = "Half-Success: \$hn_dirbasic->movefile($src,$dst)\nfile is copied to dest, but source is still alive.";
				$this->debug('dirbasic,file,dir');
				return FALSE;
			}
		}


		/** @shortdesc deletes a file
		  * @public
		  * @type bool
		  * @return True if the file was succesfully copied
		  **/
		function delfile($src)
		{
			return $this->deletefile($src);
		}
		function deletefile($src)
		{
			if(@unlink($src))
			{
				$this->msg = "Success: \$hn_dirbasic->delfile($src)";
				$this->debug('dirbasic,file,dir');
				return TRUE;
			}
			else
			{
				$this->msg = "Failed: \$hn_dirbasic->delfile($src)\n";
				$this->debug('dirbasic,file,dir');
				return FALSE;
			}
		}


		/** @shortdesc Wrapper for _makedir
		  * @public
		  * @type bool
		  * @return True, if the directory was successfuly created
		  **/
		function hn_makedir($folder,$chmodvalue=0755)
		{
			$curdir = getcwd();
			$res = $this->_makedir($folder,$chmodvalue);
			chdir($curdir);
			return $res;
		}

		/** @shortdesc Wrapper-function for _makedir(), used only from hn_ftpdir because of compatibility-purpose!
		  * @public
		  * @type bool
		  * @return True, if the directory was successfully created
		  **/
		function lmd($dirname,$chmodvalue=755)
		{
			$lastdir = getcwd();
			$res = $this->_makedir($dirname,$chmodvalue);
			chdir($lastdir);
			return $res;
		}



		/** @shortdesc creates Sub-directories A-Z and optional 0-9, with optional prepend-string
		  * @param type = string; [ alpha | numeric | alphanumeric | hex ]
		  * @public
		  * @type void
		  **/
		function make_ABC_dirs($parent_directory, $prepend='', $type='alphanumeric')
		{
			$parent_directory = $this->hn_buildfolderstr($parent_directory);
			if($type=='hex')
			{
				for($i=0;$i<10;$i++)
				{
					$folder = $parent_directory . $prepend . $i;
					if(!$this->hn_makedir($folder))
					{
						return FALSE;
					}
				}
				for($i=97;$i<103;$i++)
				{
					$folder = $parent_directory . $prepend . chr($i);
					if(!$this->hn_makedir($folder))
					{
						return FALSE;
					}
				}
				return TRUE;
			}
			if(preg_match('/alpha/',$type))
			{
				for($i=97;$i<123;$i++)
				{
					$folder = $parent_directory . $prepend . chr($i);
					if(!$this->hn_makedir($folder))
					{
						return FALSE;
					}
				}
			}
			if(preg_match('/numeric/',$type))
			{
				for($i=0;$i<10;$i++)
				{
					$folder = $parent_directory . $prepend . $i;
					if(!$this->hn_makedir($folder))
					{
						return FALSE;
					}
				}
			}
			return TRUE;
		}


		/** @shortdesc Recursive deletion of all empty directories
		  * @public
		  * @type bool
		  **/
		function DELETE_EMPTY_DIRS($rootdir)
		{
			if(!$this->hn_is_dir($rootdir))
			{
				return FALSE;
			}
			$dir_handle=opendir($rootdir);
			if($dir_handle==FALSE)
			{
				return FALSE;
			}
			$isEmpty = TRUE;
			while(($file=readdir($dir_handle)) && $isEmpty)
			{
				if($file=="." || $file=="..") continue;
				$src = $this->hn_BuildFolderStr($rootdir).$file;
				if(is_file($src))
				{
					return FALSE;
					break;
				}
				else
				{
					$isEmpty = $this->DELETE_EMPTY_DIRS($src);
				}
			}
			closedir($dir_handle);
			#return $isEmpty ? rmdir($rootdir) : FALSE;
			if($isEmpty)
			{
				echo "- Remove: $rootdir\n";
				return @rmdir($rootdir);
			}
			return FALSE;
		}


		/** @shortdesc deletion of all files, but no dirs, optionally RECURSIVE !
		  * @public
		  * @type bool
		  **/
		function DELETE_ALL_FILES_IN_DIR( $dirname, $recursive=false )
		{
			if( ! $this->hn_is_dir($dirname) )
			{
				return false;
			}

			$dir_handle = opendir( $dirname );
			while( $file = readdir( $dir_handle ) )
			{
				if( $file=="." || $file==".." )
				{
					continue;
				}
				$src = $this->hn_BuildFolderStr( $dirname ) . $file;
				if( is_file($src) )
				{
					unlink($src);
				}
				elseif( $this->hn_is_dir($src) && $recursive===true )
				{
					$this->DELETE_DIR( $src, true );
				}
			}
			closedir($dir_handle);
			return true;
		}


		/** @shortdesc Recursive deletion of all directories and files
		  * @public
		  * @type bool
		  **/
		function DELETE_DIR($dirname)
		{
			if(!$this->hn_is_dir($dirname)) return FALSE;

			if($this->isWin())
			{
				exec("cmd /C rmdir /S /Q \"$dirname\"",$a,$iRet);
				return $iRet == 0 ? TRUE : FALSE;
			}
			else
			{
				$dir_handle=opendir($dirname);
				while($file=readdir($dir_handle))
				{
					if($file=="." || $file=="..") continue;
					$src = $this->hn_BuildFolderStr($dirname).$file;
					if(is_file($src))
					{
						unlink($src);
					}
					else
					{
						$this->DELETE_DIR($src);
					}
				}
				closedir($dir_handle);
				rmdir($dirname);
				return TRUE;
			}
		}


		/** @shortdesc Recursive copy of all directories and files
		  * @public
		  * @type bool
		  **/
		function COPY_DIR($sourcedir, $targetdir, $chmod=0755)
		{
			if(!$this->hn_is_dir($sourcedir)) return FALSE;
			if(!$this->hn_makedir($targetdir, $chmod)) return FALSE;

			$dir_handle=opendir($sourcedir);
			while($file=readdir($dir_handle))
			{
				if($file=="." || $file=="..") continue;
				$src = $this->hn_BuildFolderStr($sourcedir).$file;
				$dst = $this->hn_BuildFolderStr($targetdir).$file;
				if(is_file($src)) {
					$this->copyfile($src,$dst);
				}
				else {
					$this->COPY_DIR($src,$dst);
				}
			}
			closedir($dir_handle);
			return TRUE;
		}



	/////////////////////////////////////////////
	//	PRIVATE METHODS


		/** Kann unter Windows: lokale Laufwerke a la C:/ dir oder C:\\dir
		  *                     UNC-Pfade a la //SERVER/Freigabe/dir oder \\\\SERVER\\freigabe\\dir
          *
		  * @shortdesc create directories, also with more than one none-existent subfolder
		  * @private
		  * @type bool
		  * @return True, if the directory was successfuly created
		  **/
		function _makedir($folder,$chmodvalue=0755)
		{
			if($this->hn_is_dir($folder))
			{
				$this->msg = "Folder already exists: \$hn_dirbasic->hn_makedir($folder)";
				$this->debug('dirbasic,file,dir');
				return TRUE;
			}

			$old_umask = umask(0);
			if(@mkdir($folder, $chmodvalue))
			{
				//system("chmod $chmodvalue $folder"); // maybe you need to use the system command in some cases...
				chmod($folder, $chmodvalue);
				umask($old_umask);
				$this->msg = "Folder successfully created: \$hn_dirbasic->hn_makedir($folder)";
				$this->debug('dirbasic,file,dir');
				return TRUE;
			}

			$drive = '';
			$folder = $this->nobacks(trim($folder));
			if(preg_match("/^Windows/",php_uname()))
			{
				if(substr($folder,1,1) == ':')
				{
					// Localdrive
					$newfolder = substr($folder,2);
				}
				elseif(substr($folder,0,2) == '//')
				{
					// UNC-Path | Local-Network-Resource
					$newfolder = strstr(substr($folder,2),'/');
					$newfolder = strstr(substr($newfolder,1),'/');
				}
				elseif(substr($folder,0,1) == '/' && substr($folder,1,1) != '/')
				{
					// Root of current Drive
					$newfolder = $folder;
				}
				$drive = str_replace($newfolder,'',$folder);
				$folder = $newfolder;
			}
			else
			{
				//if(substr($folder,0,2) == '//') $drive = substr($folder,0,2);
				//if(substr($folder,0,1) == '/') $drive = '';
				$folder = $folder;
			}



			// wegen OpenBaseDir-Restrictions duerfen wir nur unterhalb von $_SERVER['DOCUMENT_ROOT'] das Dateisystem mit isDir etc befragen
			if(strlen(trim(ini_get('open_basedir')))>0)
			{
				$folder = substr($folder,strlen($_SERVER['DOCUMENT_ROOT']));
				$DocRootPrepend = $_SERVER['DOCUMENT_ROOT'];
			}


			$result = FALSE;
			$tmp = explode("/",$folder);
			if(!is_array($tmp))
			{
				return FALSE;
			}
			$PathSegments = array();
			foreach($tmp as $segment)
			{
				if(trim($segment)=='') continue;
				$PathSegments[] = $segment;
			}

			if(isset($DocRootPrepend))
			{
				$curpath = $DocRootPrepend."/".$PathSegments[0];
			}
			else
			{
				$curpath = $drive."/".$PathSegments[0];
			}

#$this->my_var_dump($drive,1);
#$this->my_var_dump($folder,1);
#$this->my_var_dump($PathSegments,1);
#$this->my_var_dump($curpath,1);


			for($i = 1; $i <= count($PathSegments)-1; $i++)
			{
				$curpath = $this->hn_BuildFolderStr($curpath,$PathSegments[$i]);
				#$curpath .= '/'.$PathSegments[$i];

				if(is_dir($curpath) || $this->hn_is_dir($curpath))
				{
					continue;
				}

				if(!@mkdir($curpath, $chmodvalue))
				{
					umask($old_umask);
					$this->msg = "ERROR! Couldn't create folder: \$hn_dirbasic->hn_makedir($curpath)";
					$this->debug('error,hn_dirbasic,file,dir');
					return FALSE;
				}

				$this->msg = "Folder successfully created: \$hn_dirbasic->hn_makedir($curpath)";
				$this->debug('dirbasic,file,dir');
				//system("chmod $chmodvalue $folder"); // maybe you need to use the system command in some cases...
				chmod($curpath, $chmodvalue);
				$result = TRUE;
			}
			umask($old_umask);
			return $result;
		}



} // CLASS END hn_dirbasic

}



/** Scans directories and add all or only specific files/dirs to result arrays.
  *
  * Has many and powerful possibilities while it is very comfortable to configure and handle.
  *
  * A ConfigArray must be defined and 2 methods must called to get the result.
  *
  * You can use single strings, -Wildcards, -RegExps, or arrays containing strings and/or RegExps
  * in the configuration lists.
  *
  * You can define a list as include or exclude.
  *
  * Lists can be defined for: extensions, basenames, subdirnames.
  *
  * You can define a max recursion-depth for the scan.
  *
  * You can pass one startfolder or an array with multiple startfolders to the class.
  *
  * If nothing is specified, but only a startfolder, the complete dir will scanned recursively,
  * and all files/dirs will added to the result arrays.
  *
  * The result array for files contains an array for each matched file with
  * information about:
  *
  * 	fullname             (absolute path with filename and extension)
  *     dirname              (absolute path without filename and extension)
  *     basename             (filename and extension)
  *     extension            (fileextension)
  *     filesize             (in Byte)
  *     timestamp            (last_modified)
  *     date_last_modified   (style is defined in ClassVar '$filedate_format')
  *
  * and optionally plus:
  *
  *     basename_ne          (basename without extension)
  * 	friendlyfilesize     (like 1,3 MB | 566 Byte | 27,7 KB)
  *     URL                  (the Webserver-URL, but only if the script is running
  *                           in Webserverenvironment and the file is located
  *                           within the webservers DocumentRoot)
  *
  * If you need more, you can modify the method 'addFile_Extended()'
  *
  *
  * @shortdesc Scans directories and add files/dirs to result arrays.
  * @public
  * @author Horst Nogajski, Copyright (c) 2004
  * @version 1.0 alpha
  * @date 2004-Apr-10
  **/
if(!class_exists('hn_dir')) {

class hn_dir extends hn_dirbasic
{

	/////////////////////////////////////////////
	//	PUBLIC VARS


		/** @shortdesc Recursive SearchDepth, -1 = no limit; 0 = only one folder; 1-n = 1-n subfolderdepth
		  * @public
		  * @type integer
		  **/
		var $depth					 = -1;



		/** @shortdesc A list of file-extensions
		  * @public
		  * @type mixed [ array | string ]
		  **/
		var $fileextensions;

		/** @shortdesc A list of file-basenames
		  * @public
		  * @type mixed [ array | string ]
		  **/
		var $filebasenames;

		/** @shortdesc A list of foldernames (only basenames!)
		  * @public
		  * @type mixed [ array | string ]
		  **/
		var $subdirnames;



		/** @shortdesc This must be enabled if you want pass searchpattern as RegExp OR with Wildcards (* ?)
		  * @public
		  * @type boolean
		  **/
		var $use_regexp				  = FALSE;

		/** @shortdesc A short string which is used as prefix to define an entry in one of the lists as a Regular Expression (for preg_-functions) e.g. your RegExp '/^prog.*?$/i' becomes '/:/^prog.*?$/i'
		  * @public
		  * @type string
		  **/
		var $regexp_identifier		 = '/:';

		/** @shortdesc This handles the behave for String and Wildcard Searchpattern. This is ignored on Windows! (On windows always it is case_in_sensitive!)
		  * @public
		  * @type boolean
		  **/
		var $case_sensitive			 = FALSE;



		/** @shortdesc If is set to FALSE, only files which are in list of fileextensions will be added to resultarray. If is set to TRUE, all files where extension is not in extensionlist will be added to resultarray.
		  * @public
		  * @type boolean
		  **/
		var $fileextensions_exclude	 = FALSE;

		/** @shortdesc If is set to FALSE, only files which are in list of filebasenames will be added to resultarray. If is set to TRUE, all files which are not in filebasenames-list will be added to resultarray.
		  * @public
		  * @type boolean
		  **/
		var $filebasenames_exlude	 = FALSE;

		/** @shortdesc If is set to FALSE, only files of folders which are in folderslist be added to resultarray. If is set to TRUE, all files of folders which are not in folderslist will be added to resultarray.
		  * @public
		  * @type boolean
		  **/
		var $subdirnames_exclude	 = FALSE;

		/** @shortdesc If is set to TRUE, only files which match to all settings will be added to resultarray. If is set to FALSE, all files which match to any of the settings will be added.
		  * @public
		  * @type boolean
		  **/
		var $matchall				 = TRUE;



		/** @shortdesc Holds a format-string for the date()-function
		  * @public
		  * @type string
		  **/
		var $filedate_format		 = "Y-m-d H:i:s";

		/** @shortdesc The fieldnames in hirarchical order you wich to sort the filesarray.
		  * @public
		  * @type array
		  **/
		var $sortvalue_files		 = array('dirname','extension','basename');

		/** @shortdesc The fieldnames in hirarchical order you wich to sort the directorysarray.
		  * @public
		  * @type array
		  **/
		var $sortvalue_dirs			 = array('fullname');



		/** @shortdesc If class should stop time or not
		  * @public
		  * @type boolean
		  **/
		var $use_timer				 = FALSE;

		/** @shortdesc If class only should perform the Dircompare without filling the result arrays!
		  * @public
		  * @type boolean
		  **/
		var $benchmark				 = FALSE;



		/** If is set to TRUE, only basic informations will stored in files-Array:
		  * (fullname, dirname, basename, extension, filesize in byte, timestamp of last-modification)
		  *
		  * @shortdesc If is set to TRUE, only basic informations will stored in resultarray.
		  * @public
		  * @type boolean
		  **/
		var $basic_result			 = TRUE;


		/** If is set to TRUE, only the fullname will stored in files-Array
		  *
		  * @shortdesc If is set to TRUE, only fullname will stored in resultarray.
		  * @public
		  * @type boolean
		  **/
		var $very_basic_result		 = FALSE;


		/** Userfunction:
		  *
		  * If you need more / other file informations in ResultArray,
          * you can add the needed code to this function.
		  *
		  * @shortdesc Adds extended file information to ResultArray.
		  * @private
		  **/
		function addFile_Extended(&$a,$file)
		{
			// $a already contains:
            // fullname, filesize, timestamp, date_last_modified, dirname, basename, extension

			//$a['depth']	 		 =	 $this->getdepth($file);
			//$a['sourcename'] 		 =	 $this->sourcename;
			//$a['date_last_access'] =	 date($this->filedate_format, fileatime($file));
			$a['basename_ne']		 =	 ($a['extension']!="") ? substr($a['basename'],0,strlen($a['basename'])-strlen($a['extension'])-1) : $a['basename'];
			$a['friendlyfilesize']	 =	 $this->friendly_filesize($a['filesize']);
			$a['url']	 			 =	 $this->path2url($file);
		}



	/////////////////////////////////////////////
	//	PRIVATE VARS

		/** @private **/
		var $files;
		/** @private **/
		var $directorys;

		/** @private **/
		var $scanned_dirs;
		/** @private **/
		var $scanned_files;
		/** @private **/
		var $matched_files;
		/** @private **/
		//var $matched_dirs;

		/** @private **/
		var $dir;
		/** @private **/
		var $sourcename;

		/** @private **/
		var $local_sys;



	/////////////////////////////////////////////
	//	CONSTRUCTOR

		/** @shortdesc Constructor
		  * @public
		  **/
		function hn_Dir($config='',$secure=TRUE)
		{
			// call constructor of parent class
			$this->hn_dirbasic();
			$this->local_sys = $this->isWin() ? 'WIN' : 'UNIX';

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
					$this->msg = 'Config-Array extracted in secure-mode: $hn_Dir->CONSTRUCTOR() [ possible: '.count($valid).' | extracted: '.$extracted.' | skipped: '.(count($config)-$extracted).' ]';
					$this->debug('Dir,ini');
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
					$this->msg = 'Config-Array extracted in _UN_secure-mode: $hn_Dir->CONSTRUCTOR() [ extracted: '.count($config).' ]';
					$this->debug('Dir,ini');
				}
				$this->fileextensions = ($this->fileextensions==='' || (is_array($this->fileextensions) && count($this->fileextensions)===0)) ? NULL : $this->fileextensions;
			}
			else
			{
				$this->msg = 'Initialized without Config-Array: $hn_Dir->CONSTRUCTOR()';
				$this->debug('Dir,ini');
			}

		}



	/////////////////////////////////////////////
	//	PUBLIC METHODS


		/** After a class-instance is initiated, this method can called once or multitimes to
		  * add files and directories to the ResultArray.
		  *
		  * @shortdesc Adds all files & dirs which match the searchcreterias to the result array. Optionally the Array can cleared before starting the scan.
		  * @public
		  * @param mixed [string / array] dir The param $dir can be a string or an array with strings which contains the absolute pathes off folders which should be searched for matching files.
		  * @param boolean reset If is TRUE, result array is cleared before scanning. Otherwise current result array is kept and new matches will append.
		  **/
		function getDir( $sDir_or_aDirs, $reset=true )
		{
			if( $reset )
			{
				$this->hn_dir_reset();
			}
			if( $this->use_timer )
			{
				$this->timer_init();
			}
			if( isset($this->remote_sys) && preg_match('/.*win.*/i',$this->remote_sys) && $this->local_sys == 'WIN' )
			{
				$this->case_sensitive = FALSE;
			}
			else
			{
				$this->case_sensitive = (!isset($this->remote_sys) && $this->local_sys == 'WIN') ? FALSE : $this->case_sensitive;
			}
			$this->prepare_lists();
			foreach( (array)$sDir_or_aDirs as $v )
			{
				$v = $this->nobacks($v);
				$this->sourcename = $v;
				if( $this->hn_is_dir($v) )
				{
					$this->loadDir($v);
				}
			}
			if( $this->use_timer )
			{
				$this->timer_result();
			}
		}


		/** @shortdesc Sorts the Resultarray files
		  * @public
		  * @param array sortvalues The fieldnames in hirarchical order you wich to sort the Array.
		  **/
		function sortFiles($sortvalues="",$descending=FALSE)
		{
			if($sortvalues=="") $sortvalues = $this->sortvalue_files;
			if(is_array($sortvalues) && is_array($this->files)) $this->files = $this->hn_array_sort($this->files,$sortvalues,FALSE,$descending);
		}


		/** @shortdesc Sorts the Resultarray directories
		  * @public
		  * @param array sortvalues The fieldnames in hirarchical order you wich to sort the Array.
		  **/
		function sortDirectorys($sortvalues="")
		{
			if($sortvalues=="") $sortvalues = $this->sortvalue_dirs;
			if(is_array($sortvalues) && is_array($this->directorys)) $this->directorys = $this->hn_array_sort($this->directorys,$sortvalues);
		}




	/////////////////////////////////////////////
	//	PRIVATE METHODS


		/** @shortdesc Reset all arrays, counters and the stack
		  * @private
          **/
		function hn_dir_reset()
		{
			$this->files 			= array();
			$this->scanned_dirs 	= 0;
			$this->scanned_files 	= 0;
			$this->matched_files 	= 0;
			$this->dir 				= '';
			$this->dirFILO 			= new FILO();
			$this->directorys 		= array();
			//$this->matched_dirs 	= 0;
		}


		/** @shortdesc Initiate stack and start scanning the root dir.
		  * While stack isn't empty, checks depth & subdir name and
          * call loadFromCurDir() for valid dirs.
		  * @private
          **/
		function loadDir($dir)
		{
			// strip trailing slash!
			$this->dir = (strrpos($dir, "/") == strlen($dir) -1) ? substr($dir, 0, strrpos($dir, "/")) : $dir;
			$this->dirFILO->zero();
			$this->dirFILO->push($this->dir);
			while($this->curDir = $this->dirFILO->pop())
			{
				$depth = $this->checkdepth();
				$bname = basename($this->curDir);
					if(isset($this->subdirnames) && $this->curDir!=$this->dir && !$this->checksubdirname($bname))
					{
						$this->msg = "\$hn_Dir->loadDir() (excluded by subdirlist) Skip directory '$bname'";
						$this->debug('parseDir');
						continue;
					}
					if($this->depth > -1 && $depth > $this->depth)
					{
						$this->msg = "\$hn_Dir->loadDir() (excluded by depth) Skip directory '$bname'";
						$this->debug('parseDir');
						continue;
					}
				$this->scanned_dirs++;
				if(!$this->benchmark) $this->directorys[] = array('fullname'=>$this->curDir,'basename'=>$bname);
				$this->msg = "\$hn_Dir->loadDir() (depth=$depth) Load directory ".$this->curDir."";
				$this->debug('parseDir');
				$this->loadFromCurDir();
			}
			clearstatcache();
		}


		/** @shortdesc Scans a valid dir, add subdirs to the stack, and call checkfile for files.
		  * @private
          **/
		function loadFromCurDir()
		{
			if($handle = @opendir($this->curDir))
			{
				while(false!==($file = readdir($handle)))
				{
					if($file=="." || $file=="..") continue;
					$filePath = $this->curDir . '/' . $file;
					// dirs zum stack
					if($this->hn_is_dir($filePath))
					{
						$this->dirFILO->push($filePath);
						continue;
					}
					$this->checkfile($filePath);
				}
				closedir($handle);
			}
			else
			{
				$this->msg = "ERROR! Open Dir '".$this->curDir."'  \$hn_Dir->loadFromCurDir()";
				$this->debug('error,parseDir');
			}
		}


		/** @shortdesc calls the needed compare functions and if is a valid file, call addFile
		  * @private
          **/
		function checkfile($file)
		{
			$this->scanned_files++;
			$a = array();
			$b = array();
			$k = 0;
			if(isset($this->fileextensions))
			{
				$a['ext'] = (bool)TRUE;
				$b['ext'] = (bool)$this->checkfileextension($file);
				if($a['ext']===$b['ext']) $k++;
			}
			if(isset($this->filebasenames))
			{
				$a['base'] = (bool)TRUE;
				$b['base'] = (bool)$this->checkfilebasename($file);
				if($a['base']===$b['base']) $k++;
			}
			$i = count($a);
			if($i==$k) {
				$this->addFile($file);
				return;
			}
			if(!$this->matchall && $k>0) $this->addFile($file);
		}


		/** Adds a matching file to the ResultArray.
		  * If you need more / other file informations in the ResultArray,
          * you can set 'basic_result' to FALSE. So also the addFile_Extended
          * Funktion will executed.
		  *
		  * @shortdesc Adds information of a matching file to the ResultArray.
		  * @private
		  **/
		function addFile($file)
		{
			$this->matched_files++;
			if($this->benchmark) return;

			if($this->very_basic_result)
			{
				$this->files[] = array('fullname'=>$file);
				return;
			}

			$fileInfo = pathinfo($file);
			$file_size = filesize($file);
			$timestamp = filemtime($file);
			$a = array();
			$a['fullname']	 		 =	 $file;
			$a['filesize']	 		 =	 $file_size;
			$a['timestamp']			 =   $timestamp;
			$a['date_last_modified'] =	 date($this->filedate_format, $timestamp);
			$a['dirname']	 		 =	 $fileInfo['dirname'];
			$a['basename']	 		 =	 $fileInfo['basename'];
			$a['extension']	 		 =	 $this->extension($file);
			if(!$this->basic_result) $this->addFile_Extended($a,$file,$file_size);

			$this->files[] = $a;
		}




	/////////////////////////////////////////////////////////////////////////
	//	CompareFunktionen

		/** @private **/
		function checksubdirname($item,$recursive='')
		{
			return $this->compare_core($item,'subdirnames','subdirnames_exclude');
		}

		/** @private **/
		function checkfileextension($item,$onlyString=FALSE)
		{
			$item = $this->extension($item,$onlyString);
			return $this->compare_core($item,'fileextensions','fileextensions_exclude');
		}

		/** @private **/
		function checkfilebasename($item)
		{
			$item = basename($item);
			return $this->compare_core($item,'filebasenames','filebasenames_exlude');
		}


		/** @private **/
		function compare_core($item,$list,$exclude,$recursive='')
		{
			// Die alte Methode! Viel schneller als RegExp !!
			if(!$this->use_regexp)
			{
				$match = $this->compare_item($item,$this->{$list});
				return $this->{$exclude} ? !$match : $match;
			}

			// hier sind Strings, RegExps und Wildcards moeglich
			$against = $recursive!='' ? $recursive : $this->{$list};

			if(is_string($against))
			{
				if($this->isSimpleString($against))
				{
					// Simple-String-Compare mit Beruecksichtigung von case_sensitive
					$match = $this->compare_item($item,$against);
				}
				else
				{
					// preg_match against Regexp or Wildcards
					$pattern = $this->makePattern($against);
					$match = preg_match($pattern,$item);
				}
				return $this->{$exclude} ? !$match : $match;
			}
			elseif(is_array($against))
			{
				// SimpleString-Compare in whole array - if match, return
				if($this->compare_item($item,$against))
				{
					return $this->{$exclude} ? FALSE : TRUE;
				}

				foreach($against as $v)
				{
					// foreach array-item recursive call with string-param
					if($this->compare_core($item,$list,$exclude,$v))
					{
						return TRUE;
					}
				}
				return FALSE;
			}
		}
	//
	/////////////////////////////////////////////////////////////////////////



	/////////////////////////////////////////////////////////////////////////
	//
		/** @shortdesc prepares searchlists depending on current state of case_sensitive
		  * @private
          **/
		function prepare_lists()
		{
			if($this->case_sensitive) return;

			foreach(array('fileextensions','filebasenames','subdirnames') as $l)
			{
				if(is_array($this->{$l}))
				{
					foreach($this->{$l} as $k=>$v)
					{
						$this->{$l}[$k] = $this->prepare_item($v);
					}
				}
				elseif($this->{$l} != '')
				{
					$this->{$l} = $this->prepare_item($this->{$l});
				}
			}
		}

		/** @shortdesc sub of prepare_lists
		  * @private
          **/
		function prepare_item($item)
		{
			if(!$this->use_regexp)
			{
				return strtolower($item);
			}
			elseif($this->isSimpleString($item))
			{
				return strtolower($item);
			}
			else
			{
				return $item;
			}
		}

		/** @shortdesc detects if a searchlist item is a RegExp, contains Wildcards or is a SimpleString
		  * @private
          **/
		function isSimpleString($item)
		{
			if(substr($item,0,strlen($this->regexp_identifier)) == $this->regexp_identifier) return FALSE;
			if(preg_match('/.*[\?|\*].*/',$item)) return FALSE;
			return TRUE;
		}

		/** @shortdesc compares (string against array) or (string against string) giving respect to case_sensitive
		  * @private
          **/
		function compare_item(&$item,&$list)
		{
			if(is_array($list))
			{
				return $this->case_sensitive ? in_array($item, $list) : in_array(strtolower($item), $list);
			}
			else
			{
				return $this->case_sensitive ? $item==$list : strtolower($item)==$list;
			}
		}

		/** @shortdesc check a string from the user defined lists and (may) modify it
		  * @private
          * @return string
		  **/
		function makePattern($str)
		{
			// if is a RegExp, only cut identifier
			if(substr($str,0,strlen($this->regexp_identifier)) == $this->regexp_identifier)
			{
				return substr($str,strlen($this->regexp_identifier));
			}
			// escape special chars
			$str = str_replace(array('.','/','$'),array('\.','\/','\$'),$str);
			// replace Wildcards
			$str = '/^'.str_replace(array('?','*'),array('.+?','.*?'),$str).'$/';
			return $this->case_sensitive ? $str : $str."i";
		}
	//
	/////////////////////////////////////////////////////////////////////////




	/////////////////////////////////////////////////////////////////////////
	//

// SOLLTE NOCH MAL IN RUHE UEBERARBEITET WERDEN!!
// ist fuer UNIX-Pathnames (oder FTP) angepasst
		/** @shortdesc
		  * @private
          **/
		function getdepth($dirname)
		{
			$s = (substr($dirname,0,1) == '/') ? 'x:'.$dirname : $dirname;
			$PathSegments = explode('/',$s);
			return count($PathSegments);
		}	/*
				function getdepth($filename)
				{
					$PathSegments = explode("/",dirname($filename));
					return (int)count($PathSegments);
				}
			*/

// SOLLTE NOCH MAL IN RUHE UEBERARBEITET WERDEN!!
//!! ist NICHT fuer UNIX-Pathnames (oder FTP) getestet !!
		/** @shortdesc
		  * @private
          **/
		function checkdepth()
		{
			$s = $this->noBacks($this->curDir);
			//$s = substr($s,(strlen($s) -1),1)=='/' ? $s : $s.'/';
			//$s = str_replace('//','/',$s);
			return $this->getdepth($s) - $this->getdepth($this->dir);
		}
	//
	/////////////////////////////////////////////////////////////////////////


	/////////////////////////////////////////////////////////////////////////
	//
		/** @private **/
		function timer_init($name='')
		{
			$this->timer = new timer();
			$this->timer->timer_start($name);
		}
		/** @private **/
		function timer_result($name='',$print=TRUE)
		{
			$t = $this->timer->timer_get_current($name);
			unset($this->timer);
			if(!$print) return $t;
			if($name == 'ftp')
			{
				$this->print_timer_result('');
				$this->print_timer_result('hn_FTP-DIR results:');
				$this->print_timer_result("- scanned directories:\t".$this->ftp_scanned_dirs);
				$this->print_timer_result("- scanned files:      \t".$this->ftp_scanned_files);
				//$this->print_timer_result("- matched directories:\t".$this->ftp_matched_dirs);
				$this->print_timer_result("- matched files:      \t".$this->ftp_matched_files);
				$this->print_timer_result("- time: ".$t);
				$this->print_timer_result("----------------------------------");
				$this->print_timer_result('');
			}
			else
			{
				$this->print_timer_result('');
				$this->print_timer_result('hn_Dir results:');
				$this->print_timer_result("- scanned directories:\t".$this->scanned_dirs);
				$this->print_timer_result("- scanned files:      \t".$this->scanned_files);
				//$this->print_timer_result("- matched directories:\t".$this->matched_dirs);
				$this->print_timer_result("- matched files:      \t".$this->matched_files);
				$this->print_timer_result("- time: ".$t);
				$this->print_timer_result("----------------------------------");
				$this->print_timer_result('');
			}
		}
		/** @private **/
		function print_timer_result($msg)
		{
			$this->msg = $msg;
			$this->debug('ftp,dir,ini,timer,sys');
		}
	//
	/////////////////////////////////////////////////////////////////////////

} // CLASS END hn_Dir

}

