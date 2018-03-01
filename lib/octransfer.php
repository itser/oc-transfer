<?php
class octransfer
{
	var $zip;
	var $dump_dir = "dump"; // директория, куда будем сохранять резервную копию БД
	var $dump_name = "dump.sql"; //имя файла
	var $insert_records = 50; //записей в одном INSERT
	var $gzip = false; 		//упаковать файл дампа
	var $log;

	public function __construct() {
		if (!file_exists('dump')) {
			mkdir('dump');
		}
	}

	public function backupFiles(){
		//TODO .htaccess
		$this->zip = new ZipArchive();
		$this->zip->open( 'dump/dump.zip', ZIPARCHIVE::CREATE );
		$this->addFile($_SERVER['DOCUMENT_ROOT']);
		$this->zip->close();
		return true;
	}

	public function restoreFiles($data){
		//TODO .htaccess
		$zip = new ZipArchive();
		if ($zip->open('dump/dump.zip') === true){

			$new_config_lines = array(
				30 => "define('DB_HOSTNAME', '" . $data['bd_host'] . "');",
				31 => "define('DB_USERNAME', '" . $data['bd_user'] . "');",
				32 => "define('DB_PASSWORD', '" . $data['bd_pass'] . "');",
				33 => "define('DB_DATABASE', '" . $data['bd_name'] . "');",
				35 => "define('DB_PREFIX', '" . $data['bd_prefix'] . "');",
			);

			$filename = $_SERVER['DOCUMENT_ROOT'] . '/octransfer/config/oc2/admin/config.php';
			$file = file($filename);
			foreach ($new_config_lines as $line => $replace){
				$file[$line-1] = $replace.PHP_EOL;
			}
			file_put_contents($filename, join('', $file));

			$filename = $_SERVER['DOCUMENT_ROOT'] . '/octransfer/config/oc2/config.php';
			$file = file($filename);
			foreach ($new_config_lines as $line => $replace){
				$file[$line-1] = $replace.PHP_EOL;
			}
			file_put_contents($filename, join('', $file));


			//TODO при повторном вызове перезатрутся старые конфиги
			$zip->renameName('admin/config.php','admin/config_old.php');
			$zip->renameName('config.php','config_old.php');

			$zip->addFile($_SERVER['DOCUMENT_ROOT'] . '/octransfer/config/oc2/admin/config.php', 'admin/config.php');
			$zip->addFile($_SERVER['DOCUMENT_ROOT'] . '/octransfer/config/oc2/config.php', 'config.php');
			$zip->close();

			if ($zip->open('dump/dump.zip') === true){
				$zip->extractTo('../');
				$zip->close();
			}
		}else{
			echo 'Не могу найти файл архива!';
			exit;
		}
		return true;
	}

	public function backupSqlBase($data){
		$link = mysqli_connect($data['bd_host'], $data['bd_user'], $data['bd_pass'], $data['bd_name']) or die( "Сервер базы данных не доступен" );
		$res = mysqli_query($link, "SHOW TABLES") or die( "Ошибка при выполнении запроса: ".mysqli_error() );
		$fp = fopen( $this->dump_dir."/".$this->dump_name, "w" );

		while( $table = mysqli_fetch_row($res) )
		{
			if ($fp)
			{
				$res1 = mysqli_query($link, "SHOW CREATE TABLE ".$table[0]);
				$row1=mysqli_fetch_row($res1);
				$query="\nDROP TABLE IF EXISTS `".$table[0]."`;\n".$row1[1].";\n";
				fwrite($fp, $query); $query="";
				$r_ins = mysqli_query($link,'SELECT * FROM `'.$table[0].'`') or die("Ошибка при выполнении запроса: ".mysqli_error());
				if(mysqli_num_rows($r_ins)>0){
					$query_ins = "\nINSERT INTO `".$table[0]."` VALUES ";
					fwrite($fp, $query_ins);
					$i=1;
					while( $row = mysqli_fetch_row($r_ins) )
					{ $query="";
						foreach ( $row as $field )
						{
							if ( is_null($field) )$field = "NULL";
							else $field = "'".mysqli_escape_string($link, $field)."'";
							if ( $query == "" ) $query = $field;
							else $query = $query.', '.$field;
						}
						if($i>$this->insert_records){
							$query_ins = ";\nINSERT INTO `".$table[0]."` VALUES ";
							fwrite($fp, $query_ins);
							$i=1;
						}
						if($i==1){$q="(".$query.")";}else $q=",(".$query.")";
						fwrite($fp, $q); $i++;
					}
					fwrite($fp, ";\n");
				}
			}
		} fclose ($fp);

		if($this->gzip){
			$data=file_get_contents($this->dump_dir."/".$this->dump_name);
			$data = gzencode($data, 9);
			unlink($this->dump_dir."/".$this->dump_name);
			$ofdot=".gz";
			$fp = fopen($this->dump_dir."/".$this->dump_name.$ofdot, "w");
			fwrite($fp, $data);
			fclose($fp);

		}
		return true;
	}

	public function restoreSqlBase($data){
		$link = mysqli_connect($data['bd_host'], $data['bd_user'], $data['bd_pass'], $data['bd_name']) or die( "Сервер базы данных не доступен" );
		$res = mysqli_query($link, "SHOW TABLES") or die( "Ошибка при выполнении запроса: ".mysqli_error() );

		//TODO разархивировать файл .gz
		$dump=file_get_contents('dump/dump.sql');
		$q='';
		$state=0;
		for($i=0;$i<strlen($dump);$i++){
			switch($dump{$i}){
				case '"':
					if($state==0) $state=1;
					elseif($state==1) $state=0;
					break;
				case "'":
					if($state==0) $state=2;
					elseif($state==2) $state=0;
					break;
				case "`":
					if($state==0) $state=3;
					elseif($state==3) $state=0;
					break;
				case ";":
					if($state==0) {
						//echo $q."\n;\n";
						mysqli_query($link, $q);
						$q='';
						$state=4;
					}
					break;
				case "\\":
					if(in_array($state,array(1,2,3)))
						$q.=$dump[$i++];
					break;
			}
			if($state==4) $state=0;else $q.=$dump{$i};
		}
	}

	private function addFile($path){
		$dir_exceptions = array(
			'octransfer',
			'image/cache',
			'vqmod/vqcache'
		);

		if ( $content_cat = glob( $path.'/*') )
		{
			foreach ( $content_cat as $key => $object )
			{
				if ( is_dir( $object ) ) {
					$path = str_replace($_SERVER['DOCUMENT_ROOT'] . '/','', $object);
					if (in_array($path,  $dir_exceptions)){
						unset($content_cat[$key]);
						continue;
					}
					$this->addFile($object);
				}
				else {
					$file_name = str_replace($_SERVER['DOCUMENT_ROOT'] . '/','', $object);
					//TODO кириллица
					//$file_name = iconv( "windows-1251", "cp866", $file_name);
					$this->zip->addFile($object, $file_name);
				}
			}
		}
		return true;
	}

	//alternative --------------------------------------------------------------------
	private function export_db(){
		set_time_limit(0);
		$dump=fopen(dirname(__FILE__).'/dump.sql','w');

		function dump($sql) {
			global $dump;
			fwrite($dump,$sql);
			ob_flush();
		}

		function trace($msg)
		{
			echo $msg.'<br>';
			ob_flush();
		}

		mysql_connect('localhost','user','pass');
		mysql_select_db('db');
		mysql_query('set names utf8');

		$res=mysql_query('show tables');

		while($tbl=mysql_fetch_array($res))
		{
			$table=$tbl[0];
			$r=mysql_query('show create table `'.mysql_real_escape_string($table).'`');
			$struct=mysql_fetch_array($r);
			$sql_struct[$table]=$struct[1].';';
		}


		dump("set names utf8;\n");

		foreach($sql_struct as $tbl_name=>$crt_str){
			trace('Экспортирую '.$tbl_name);
			dump("DROP TABLE IF EXISTS `".$tbl_name."`;\n");
			dump($crt_str."\n");
			dump("LOCK TABLES `".$tbl_name."` WRITE;\n");
			mysql_query('LOCK TABLES `'.$tbl_name.'` READ');
			$res=mysql_query('select * from `'.$tbl_name.'`');
			$insert_str='insert into `'.$tbl_name.'` values ';
			while($item=mysql_fetch_assoc($res)){
				foreach($item as $k=>$v){
					$item[$k]=mysql_real_escape_string($v);
				}
				dump($insert_str.'("'.implode('","',$item).'");'."\n");
			}
			dump("UNLOCK TABLES;\n");
			mysql_query('UNLOCK TABLES');
		}

		dump('-- end of export');
		trace('Все таблицы были успешно экспортированы');
	}

}
