<?php
	class magmaUser{
		private $logged = null;
		private $socketResource;
		private $id;
		private $ip;
		private $magmaDataFolder = "data/";
		private $currentDataFolder = null;
		public $active = false;
		public function __construct( /*(socket)*/ $socketResource, /*(int)*/$id){
			$this->socketResource = $socketResource;
			$this->id = $id;
			socket_getpeername($socketResource, $this->ip);
			echo date('Y-m-d H:i:s'). " > ".($this->ip)." connected\n";
		}
		public function login($username, $password){
			$success = false;
			if($this->loginTriesCountdown > 0)
				if(file_exists("users.dat") && filesize("users.dat") > 0){
					$users = fopen("users.dat", "r");
					while(!$success && ftell($users) < filesize("users.dat")){
						$ip = unpack('C', fread($users, 1))[1];
						$ip .= ".".unpack('C', fread($users, 1))[1];
						$ip .= ".".unpack('C', fread($users, 1))[1];
						$ip .= ".".unpack('C', fread($users, 1))[1];
						$name = fread($users, unpack('C', fread($users, 1))[1]);
						$salt = fread($users, unpack('C', fread($users, 1))[1]);
						$folder = fread($users, unpack('C', fread($users, 1))[1]);
						if($ip == $this->ip || $ip == "0.0.0.0")
						if($username === $name)
						if($salt == crypt($password, $salt)){
							$success = true;
							$this->logged = $name;
							$this->active = true;
							$this->currentDataFolder = $folder;
							echo date('Y-m-d H:i:s'). " > ".($this->ip)." logged in as ".$name.". Directory: '".$folder."'\n";
							if(!file_exists($this->magmaDataFolder)){
								mkdir($this->magmaDataFolder);
								echo date('Y-m-d H:i:s'). " > Directory ".($this->magmaDataFolder)." just was created.\n";
							}
							if(!file_exists($this->magmaDataFolder.$this->currentDataFolder)){
								mkdir($this->magmaDataFolder.$this->currentDataFolder);
								echo date('Y-m-d H:i:s'). " > Directory ".($this->magmaDataFolder).($this->currentDataFolder)." just was created.\n";
							}
						}
					}
					fclose($users);
				}
			
			if(!$success){
				echo date('Y-m-d H:i:s'). " > ".($this->ip)." tried to login as ".$username."\n";
				if($this->loginTriesCountdown > 0)
					$this->loginTriesCountdown--;
			}
			
			return 0;
		}
		public function onMessage($action, $location, $id, $variable, $value){
			$answer = "";
			$errorCode = 0;
			/*
				0   - fine; no answer
				1   - invalid action
				2   - invalid location
				4   - out of range error
				8   - answer type A/B
				16 A - take the next as bool true/false instead array/list
				32 A - array/dictionary answer
				16 & 32 B: (>> 4)
					1 - there is single-word answer
					2 - 
					3 - 
				64  - unknown error
				128 - invalid / missing parameters
				// i'll reorder them later. maybe
			*/
			//var_dump($action, $location, $id, $variable, $value);
			if($this->logged !== null){
				$directory = ($this->magmaDataFolder) . ($this->currentDataFolder);
				foreach($location as $dir){
					if(preg_match("#^[a-zA-Z0-9]+$#", $dir)){
						if(file_exists($directory.$dir."/")){
							$directory .= $dir."/";
						}else $errorCode = $errorCode | 2;
					}else $errorCode = $errorCode | 2;
				}
				$location = $directory;
				unset($directory);
				
				if(isset($id) && !is_numeric($id)){
					$errorCode = $errorCode | 128;
				}
				if(isset($variable) && (!is_string($variable) || strlen($variable) > 255 || strlen($variable) < 1)){
					$errorCode = $errorCode | 128;
				}
				if(isset($value) && !is_string($value)){
					$errorCode = $errorCode | 128;
				}
				
				if(!$errorCode && !file_exists($location."data.dat")){
					$a = fopen($location."data.dat", "c");
					fwrite($a, pack('C', 0).pack('C', 0));
					fclose($a);
				}
				
				if(!$errorCode)
				switch($action & 31){
					case 1:{ // get data
						if(!isset($id) && !isset($variable) && !isset($value)){
							// list of columns
							$r = fopen($location."data.dat", "r");
							$count = unpack('C', fread($r, 1))[1] * 256 + unpack('C', fread($r, 1))[1];
							$colums = [];
							for($i = 0; $i < $count; $i++){
								$colums[] = fread($r, unpack('C', fread($r, 1))[1]);
							}
							$ids = [];
							while(true){
								$a = fread($r, 4);
								if(strlen($a) != 4) break;
								$current = unpack('N', $a)[1];
								$ids[] = $current;
								for($i = 0; $i < $count; $i++){
									$a = fread($r, 4);
									$len = unpack('N', $a)[1];
									if($len > 0) fread($r, $len);
								}
							}
							
							$answer = pack('N', count($ids));
							for($i = 0; $i < count($ids); $i++){
								$l = "".$ids[$i];
								$answer .= pack('N', strlen($l)).$l;
							}
							fclose($r);
							$errorCode = $errorCode | 8;
							break;
						}
						if(isset($id) && isset($variable) && isset($value)){
							// boolean
							$errorCode = $errorCode | 24;
							$answer = false;
							$r = fopen($location."data.dat", "r");
							$count = unpack('C', fread($r, 1))[1] * 256 + unpack('C', fread($r, 1))[1];
							$colums = [];
							for($i = 0; $i < $count; $i++){
								$l = unpack('C', fread($r, 1))[1];
								$colums[] = fread($r, $l);
							}
							if(!in_array($variable, $colums)){
								fclose($r);
								break;
							}
							while(true){
								$a = fread($r, 4);
								if(strlen($a) != 4) break;
								$current = unpack('N', $a)[1];
								for($i = 0; $i < $count; $i++){
									$l = unpack('N', fread($r, 4))[1];
									if($l > 0)
										$w = fread($r, $l);
									else $w = "";
									if($current == $id)
										if($i == array_search($variable, $colums)){
											$answer = (($w == $value)?true:false);
											break 2;
										}
								}
							}
							
							fclose($r);
							break;
						}
						if(!isset($id) && isset($variable) && isset($value)){
							// array of ids
							$errorCode = $errorCode | 8;
							$r = fopen($location."data.dat", "r");
							$count = unpack('C', fread($r, 1))[1] * 256 + unpack('C', fread($r, 1))[1];
							$colums = [];
							for($i = 0; $i < $count; $i++){
								$l = unpack('C', fread($r, 1))[1];
								$colums[] = fread($r, $l);
							}
							if(!in_array($variable, $colums)){
								$answer = pack('N', 0);
								fclose($r);
								break;
							}
							$hand = [];
							while(true){
								$a = fread($r, 4);
								if(strlen($a) != 4) break;
								$current = unpack('N', $a)[1];
								for($i = 0; $i < $count; $i++){
									$l = unpack('N', fread($r, 4))[1];
									if($l > 0)
										$w = fread($r, $l);
									else $w = "";
									if($i == array_search($variable, $colums))
										if($w == $value)
											$hand[] = $current;
								}
							}
							fclose($r);
							$answer = pack('N', count($hand));
							for($i = 0; $i < count($hand); $i++)
								$answer .= pack('N', strlen($hand[$i])).$hand[$i];
							
							break;
						}
						if(isset($id) && isset($variable) && !isset($value)){
							// value
							$errorCode = $errorCode | 16;
							$answer = false;
							$r = fopen($location."data.dat", "r");
							$count = unpack('C', fread($r, 1))[1] * 256 + unpack('C', fread($r, 1))[1];
							$colums = [];
							for($i = 0; $i < $count; $i++){
								$l = unpack('C', fread($r, 1))[1];
								$colums[] = fread($r, $l);
							}
							if(!in_array($variable, $colums)){
								fclose($r);
								break;
							}
							while(true){
								$a = fread($r, 4);
								if(strlen($a) != 4) break;
								$current = unpack('N', $a)[1];
								for($i = 0; $i < $count; $i++){
									$l = unpack('N', fread($r, 4))[1];
									if($l > 0)
										$w = fread($r, $l);
									else $w = "";
									if($current == $id)
										if($i == array_search($variable, $colums)){
											$answer = pack('N', strlen($w)).$w;
											break 2;
										}
								}
							}
							
							fclose($r);
							break;
						}
						if(isset($id) && !isset($variable) && !isset($value)){
							// list of values per column
							$errorCode = $errorCode | 40;
							$r = fopen($location."data.dat", "r");
							$count = unpack('C', fread($r, 1))[1] * 256 + unpack('C', fread($r, 1))[1];
							$colums = [];
							for($i = 0; $i < $count; $i++){
								$l = unpack('C', fread($r, 1))[1];
								$colums[] = fread($r, $l);
							}
							$hand = [];
							while(true){
								$a = fread($r, 4);
								if(strlen($a) != 4) break;
								$current = unpack('N', $a)[1];
								for($i = 0; $i < $count; $i++){
									$l = unpack('N', fread($r, 4))[1];
									if($l > 0)
										$w = fread($r, $l);
									else $w = "";
									if($current == $id){
										$hand[$colums[$i]] = $w;
									}
								}
								if($current == $id) break;
							}
							fclose($r);
							
							$answer = pack('N', count($hand));
							foreach($hand as $k => $v)
								$answer .= pack('N', strlen($k)).$k.pack('N', strlen($v)).$v;
							
							break;
						}
						if(!isset($id) && isset($variable) && !isset($value)){
							// list of values per id
							$errorCode = $errorCode | 40;
							$r = fopen($location."data.dat", "r");
							$count = unpack('C', fread($r, 1))[1] * 256 + unpack('C', fread($r, 1))[1];
							$colums = [];
							for($i = 0; $i < $count; $i++){
								$l = unpack('C', fread($r, 1))[1];
								$colums[] = fread($r, $l);
							}
							if(!in_array($variable, $colums)){
								$answer = pack('N', 0);
								fclose($r);
								break;
							}
							$hand = [];
							while(true){
								$a = fread($r, 4);
								if(strlen($a) != 4) break;
								$current = unpack('N', $a)[1];
								for($i = 0; $i < $count; $i++){
									$l = unpack('N', fread($r, 4))[1];
									if($l > 0)
										$w = fread($r, $l);
									else $w = "";
									if($i == array_search($variable, $colums)){
										$hand[$current] = $w;
									}
								}
							}
							fclose($r);
							
							$answer = pack('N', count($hand));
							foreach($hand as $k => $v)
								$answer .= pack('N', strlen($k)).$k.pack('N', strlen($v)).$v;
							break;
						}
						$errorCode = $errorCode | 128;
					}break;
					case 2:{ // set data
						if(!isset($id) && !isset($value) && !isset($variable)){
							$errorCode = $errorCode | 16;
							
							$r = fopen($location."data.dat", "r");
							copy($location."data.dat", $location."data.tmp");
							$w = fopen($location."data.tmp", "a");
							$count = unpack('C', fread($r, 1))[1] * 256 + unpack('C', fread($r, 1))[1];
							$colums = [];
							for($i = 0; $i < $count; $i++){
								$colums[] = fread($r, unpack('C', fread($r, 1))[1]);
							}
							
							$ids = [];
							while(true){
								$a = fread($r, 4);
								if(strlen($a) != 4) break;
								$current = unpack('N', $a)[1];
								$ids[] = $current;
								for($i = 0; $i < $count; $i++){
									$a = fread($r, 4);
									$len = unpack('N', $a)[1];
									if($len > 0) fread($r, $len);
								}
							}
							$id = -1;
							do $id++; while(in_array($id, $ids) !== false);
							unset($ids);
							
							if($id >= 4294967294){
								$errorCode = $errorCode | 4;
								break;
							}else $answer = pack('N', strlen($id)).$id;
							
							fwrite($w, pack('N', $id));
							for($i = 0; $i < $count; $i++)
								fwrite($w, pack('N', 0));
							
							fclose($w);
							fclose($r);
							
							unlink($location."data.dat");
							rename($location."data.tmp", $location."data.dat");
							
							break;
						}
						if(!isset($variable) || !isset($value)){
							$errorCode = $errorCode | 128;
							break;
						}
						if(isset($id) && $id >= 4294967294){
							$errorCode = $errorCode | 4;
							break;
						}
						$r = fopen($location."data.dat", "r");
						$w = fopen($location."data.tmp", "w");
						$count = unpack('C', fread($r, 1))[1] * 256 + unpack('C', fread($r, 1))[1];
						$colums = [];
						for($i = 0; $i < $count; $i++){
							$colums[] = fread($r, unpack('C', fread($r, 1))[1]);
						}
						if(!isset($id)){
							$errorCode = $errorCode | 16;
							$pos = ftell($r);
							$ids = [];
							while(true){
								$a = fread($r, 4);
								if(strlen($a) != 4) break;
								$current = unpack('N', $a)[1];
								$ids[] = $current;
								for($i = 0; $i < $count; $i++){
									$a = fread($r, 4);
									$len = unpack('N', $a)[1];
									if($len > 0) fread($r, $len);
								}
							}
							fseek($r, $pos, SEEK_SET);
							$id = -1;
							do $id++; while(in_array($id, $ids) !== false);
							unset($ids, $pos);
							if($id >= 4294967294){
								$errorCode = $errorCode | 4;
								break;
							}else $answer = pack('N', strlen($id)).$id;
						}
						
						$newColums = $colums;
						$newCount = $count;
						if(!in_array($variable, $colums) && $count < 65535){
							$newColums[] = $variable;
							$newCount++;
						}
						if(!in_array($variable, $colums) && $count == 65535){
							$errorCode = $errorCode | 4;
						}
						fwrite($w, pack('C', floor(($newCount) / 256)).pack('C', ($newCount) % 256));
						
						foreach($newColums as $col){
							fwrite($w, pack('C', strlen($col)).$col);
						}
						
						$flag = true;
						while(true){
							$a = fread($r, 4);
							if(strlen($a) != 4) break;
							
							fwrite($w, $a);
							$current = unpack('N', $a)[1];
							if($current != $id){
								for($i = 0; $i < $count; $i++){
									$a = fread($r, 4);
									$len = unpack('N', $a)[1];
									fwrite($w, $a.(($len > 0)?fread($r, $len):""));
								}
								if($count != $newCount)
									fwrite($w, pack('N', 0));
							}else{
								$flag = false;
								$data = [];
								for($i = 0; $i < $count; $i++){
									$len = unpack('N', fread($r, 4))[1];
									$data[] = ($len > 0)?fread($r, $len):"";
								}
								if($count != $newCount)
									$data[] = $value;
								else if(in_array($variable, $newColums)){
									$data[array_search($variable, $newColums)] = $value;
								}
								foreach($data as $d){
									fwrite($w, pack('N', strlen($d)).$d);
								}
							}
						}
						fclose($r);
						if($flag){
							fwrite($w, pack('N', $id));
							$index = array_search($variable, $newColums);
							for($i = 0; $i < $newCount; $i++){
								if($index == $i)
									fwrite($w, pack('N', strlen($value)).$value);
								else
									fwrite($w, pack('N', 0));
							}
						}
						fclose($w);
						if(!($errorCode & 239)){
							unlink($location."data.dat");
							rename($location."data.tmp", $location."data.dat");
						}
					}break;
					case 3:{ // remove data
						if(isset($value) || (!isset($variable) && !isset($id))){
							$errorCode = $errorCode | 128;
							break;
						}
						$r = fopen($location."data.dat", "r");
						$w = fopen($location."data.tmp", "w");
						$count = unpack('C', fread($r, 1))[1] * 256 + unpack('C', fread($r, 1))[1];
						$colums = [];
						for($i = 0; $i < $count; $i++)
							$colums[] = fread($r, unpack('C', fread($r, 1))[1]);
						$removeCol = -1;
						$removeOnlyCell = false;
						$removeId = false;
						if(isset($id))
							$removeId = true;
						if(isset($variable) && in_array($variable, $colums))
							$removeCol = array_search($variable, $colums);
						if($removeCol == false)
							$removeCol = -1;
						if($removeCol != -1 && $removeId)
							$removeOnlyCell = true;
						
						if($removeCol != -1 && !$removeOnlyCell)
							fwrite($w, pack('C', floor(($count - 1) / 256)).pack('C', ($count - 1) % 256));
						else fwrite($w, pack('C', floor(($count) / 256)).pack('C', ($count) % 256));
						
						for($i = 0; $i < $count; $i++)
							if($removeCol != $i || $removeOnlyCell)
								fwrite($w, pack('C', strlen($colums[$i])).$colums[$i]);
						
						while(true){
							$a = fread($r, 4);
							if(strlen($a) != 4) break;
							$current = unpack('N', $a)[1];
							if(!($removeId && !$removeOnlyCell && $current == $id)){
								fwrite($w, $a);
								for($i = 0; $i < $count; $i++){
									$a = fread($r, 4);
									$len = unpack('N', $a)[1];
									$t = ($len > 0)?fread($r, $len):"";
									if(!($removeCol == $i && !$removeOnlyCell)){
										if(!($removeOnlyCell && $current == $id && $removeCol == $i))
											fwrite($w, $a.$t);
										else fwrite($w, pack('N', 0));
									}
								}
							}else{
								for($i = 0; $i < $count; $i++){
									$a = fread($r, 4);
									$len = unpack('N', $a)[1];
									if($len > 0)
										fread($r, $len);
								}
							}
						}
						fclose($r);
						fclose($w);
						if($errorCode == 0){
							unlink($location."data.dat");
							rename($location."data.tmp", $location."data.dat");
						}
					}break;
					case 4:{ // make group
						if($value == null)  $errorCode = $errorCode | 128;
						else if(preg_match("#^[a-zA-Z0-9]+$#", $value)){
							if(!file_exists($location.$value)){
								if(mkdir($location.$value))
									echo date('Y-m-d H:i:s'). " > Directory ".($location.$value)." just was created.\n";
								else
									$errorCode = $errorCode | 64;
							}
						}else{
							$errorCode = $errorCode | 128;
						}
					}break;
					case 5:{ // remove group
						if($value == null)  $errorCode = $errorCode | 128;
						else if(preg_match("#^[a-zA-Z0-9]+$#", $value)){
							if(file_exists($location.$value) && is_dir($location.$value)){
								
								// Deleting the default files
								if(count(scandir($location.$value)) == 3)
									if(file_exists($location.$value."data.dat"))
										unlink($location.$value."data.dat");
									
								if(count(scandir($location.$value)) == 2 && rmdir($location.$value)){
									echo date('Y-m-d H:i:s'). " > Directory ".($location.$value)." was deleted.\n";
								}else{
									if(count(scandir($location.$value)) != 2)
										echo date('Y-m-d H:i:s'). " > Unable to delete not-empty directory ".($location.$value)."\n";
									$errorCode = $errorCode | 64;
								}
							}else if(!is_dir($location.$value)) $errorCode = $errorCode | 64;
						}else $errorCode = $errorCode | 128;
					}break;
					case 6:{ // list groups
						$i = 0;
						foreach(scandir($location) as $item){
							if($item != "." && $item != ".." && is_dir($location.$item) && $i < 255){
								$answer .= pack('N', strlen($item)).$item;
								$i++;
							}
						}
						$answer = pack('N', $i).$answer;
						$errorCode = $errorCode | 8;
					}break;
					default:{
						$errorCode = $errorCode | 1;
					}break;
				}
				/*
				{
					action: mkgroup
					location: []
					name: "entityname"
					values: (mixed)
				}
				{
					action: getEntity
					location: []
					name: "entityname"
				}
				{
					action: get | set,
					location:[('group name', ('sub-group name'))],
					"name": "entityname"
					"variable": varname,
					"value": (mixed),
					// if action == get & name == unset it searchs trought all
				}
				{
					success: bool,
					respond: [results]
				}
				action: 00000
					0: login,
					1: getData,
					2: setData,
					3: removeData,
					4: makeGroup,
					5: removeGroup,
					6: 
					7: 
				has id & 32
				has variable: & 64
				has value: & 128
				location: [group, subgroup, etc..]
				id: int
				variable: string
				value: string
				
				[byte action & has val/var/id][json Array location][4 byte int id][0000:variable][0000:value]
				[byte action == 0][0000:username][0000:password]
				
				// [bit success & 2*bit respond][]
				*/
			}
			
			if($errorCode & 199)
				echo ($this->ip)." >> Error code ".decbin($errorCode)."\n";
			else if($errorCode & 8 && $errorCode & 16){
				$errorCode = $errorCode & 223;
				if($answer) $errorCode = $errorCode | 32;
				$answer = "";
			}
			return pack('C', $errorCode).$answer;
		}
		private $loginTriesCountdown = 1;
		public function onDisconnect(){
			echo date('Y-m-d H:i:s'). " > ".($this->ip)." disconnected\n";
		}
	}
	
