<?php
	// this is the original magma client file
	class MagmaClient{
		private $authorized = false;
		private $connection = null;
		private $lastErrCode = 0;
		public $broadcastErrors = true;
		public function connectURL($url, $port){
			$this->connection = socket_create(AF_INET, SOCK_STREAM, 0);
			$ans = false;
			try{
				$ans = @socket_connect($this->connection, $url, $port);
			}catch(Exception $e){
				error_log("Caught exception: ".$e->getMessage());
				$this->connection = null;
			};
			return $ans?true:false;
		}
		private function write($action, $location, $id, $variable, $value){
			if($this->connection != null){
				//$a = JSON_encode($data);
				//socket_write($this->connection, dechex(strlen($a))."tj".$a);
				switch($action){
					case "login":
						$action = 0;
						$username = $location;
						$password = $id;
						
						$location = null;
						$id = null;
						break;
					case "get": case "getData": $action = 1; break;
					case "set": case "setData": $action = 2; break;
					case "rm": case "removeData": $action = 3; break;
					case "mkgroup": case "makeGroup": $action = 4; break;
					case "rmgroup": case "removeGroup": $action = 5; break;
					case "ls": case "listGroups": $action = 6; break;
					default: return false;
				}
				if(isset($id)) $action = $action + 32;
				if(isset($variable)) $action = $action + 64;
				if(isset($value)) $action = $action + 128;
				
				
				$message = pack('C', $action);
				if($action > 0){
					$message .= pack('C', count($location));
					foreach($location as $group){
						$message .= pack('C', strlen($group)).$group;
					}
					if($action & 32)
						$message .= pack('N', $id);
					if($action & 64)
						$message .= pack('N', strlen($variable)).$variable;
					if($action & 128)
						$message .= pack('N', strlen($value)).$value;
				}else{
					$message .= pack('N', strlen($username)).$username;
					$message .= pack('N', strlen($password)).$password;
				}
				$ans = false;
				try{
					$ans = socket_write($this->connection, $message);
				}catch(Exception $e){
					error_log("Caught exception: ".$e->getMessage());
					$this->connection = null;
				};
				
				return $ans?true:false;
			}else
				return false;
		}
		public function lastError(){
			$reasons = [];
			if($this->lastErrCode & 1)
				$reasons[] = "Invalid action";
			if($this->lastErrCode & 2)
				$reasons[] = "Invalid (not existing or forbidden) location";
			if($this->lastErrCode & 4)
				$reasons[] = "Out of range";
			if($this->lastErrCode & 64)
				$reasons[] = "Unknown error";
			if($this->lastErrCode & 128)
				$reasons[] = "Invalid (or missing) parameter";
			return $reasons;
		}
		private function read(){
			if($this->connection != null){
				$status = @socket_read($this->connection, 1);
				$this->lastErrCode = 64;
				if($status === false) return null;
				$status = unpack('C', $status)[1];
				if($status == 0) return true;
				if($status & 199){
					$this->lastErrCode = $status;
					if($this->broadcastErrors){
						echo "Magma error: \n"; 
						foreach($this->lastError() as $a){
							echo $a."\n";
						}
					}
					return null;
				}
				if($status & 8){
					if($status & 16)
						return ($status & 32)?true:false;
					$len = unpack('N', socket_read($this->connection, 4))[1];
					$ans = [];
					for($i = 0; $i < $len; $i++){
						$k = $i;
						if($status & 32){
							$l = unpack('N', socket_read($this->connection, 4))[1];
							$k = socket_read($this->connection, $l);
						}
						$l = unpack('N', socket_read($this->connection, 4))[1];
						$ans[$k] = socket_read($this->connection, $l);
					}
					return $ans;
				}
				if($status & 48 && !($status & 207)){
					switch($status >> 4){
						case 1:
							$l = unpack('N', socket_read($this->connection, 4))[1];
							$r = socket_read($this->connection, $l);
							return $r;
							break;
						case 2:
							break;
						case 3:
							
							break;
					}
				}
				return true;
			}
			// some very cool decoding here
			
			/*$msg = ltrim($msg);
				while(strpos($msg, 't') === false)
					$msg .= @socket_read($this->connection, 128);
				// like: [length in hex][t][s|j][data]
				$bit = strpos($msg, 't');
				$len = intval(substr($msg, 0, $bit), 16);
				$method = substr($msg, $bit + 1, 1);
				$msg = substr($msg, $bit + 2);
				
				if(strlen($msg) < $len)
					$msg .=  @socket_read($socket, $len - strlen($msg));
				if($method == "s"){
					$msg = unserialize($msg);
				}else if($method == "j"){
					$msg = JSON_decode($msg, true);
				}
			*/
			return null;
		}
		public function disconnect(){
			if($this->connection != null)
				socket_close($this->connection);
			$this->connection = null;
		}
		public function login($username, $password){
			if(is_string($username))
				if(is_string($password))
					if($this->write("login", $username, $password, null, null)){
						$success = $this->read();
						if($success){
							$this->authorized = true;
						}
						return $success;
					}
			return false;
		}
		public function query($action, $location = Array(), $id = null, $variable = null, $value = null){
			if($this->connection == null)
				return "Already disconnected.";
			if($this->authorized)
				if(is_array($location))
					if((is_numeric($id) && $id < 4294967294 && $id >= 0) || $id == null)
						if(is_string($variable) || $variable == null)
							if(is_string($value) || $value == null)
								if($this->write($action, $location, $id, $variable, $value))
									return $this->read();
			if($action == "login" && !$this->authorized)
				return $this->login($location, $id);
			return false;
		}
		public function mkgroup($location, $name){
			return $this->query("mkgroup", $location, null, null, $name);
		}
		public function rmgroup($location, $name){
			return $this->query("rmgroup", $location, null, null, $name);
		}
		public function ls($location = null){
			if($location == null) $location = array();
			return $this->query("ls", $location, null, null, null);
		}
		public function set($location, $id = null, $variable = null, $value = null){
			// sets single cell.
			// if the column doesnt exists, addes it.
			// if the row doesnt exists, addes it.
			// if the id doesnt exists, selects the first free one.
			// rows arent ordered according to their number
			// the number must be unsigned 4B
			// the variable must be less than 256 symbols
			// the value's length must be unsigned 4B
			// if all are empty, it registers an empty id and returns it.
			return $this->query("set", $location, $id, $variable, $value);
		}
		public function rm($location, $id = null, $variable = null){
			// variable or id
			// if both, the cell is emptied
			// if variable, the column is removed
			// if id the row is removed
			// value is ignored
			return $this->query("rm", $location, $id, $variable, null);
		}
		public function rem($location, $id = null, $variable = null){
			return $this->query("rm", $location, $id, $variable, null);
		}
		public function remove($location, $id = null, $variable = null){
			return $this->query("rm", $location, $id, $variable, null);
		}
		public function get($location, $id = null, $variable = null, $value = null){
			// $id -> returns list of values per column
			// $variable -> returns list of values per ids
			// $id + $variable -> returns value
			// $variable + $value -> returns array of ids
			// $id + $variable + $value -> returns boolean
			// nothing -> returns array of ids
			return $this->query("get", $location, $id, $variable, $value);
		}
		public function version(){
			return "0.0.5";
		}
		// public connectPID(){} - to be added in future release
	}
	