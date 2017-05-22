<?php
	if(!function_exists('json_encode'))
	{
		function json_encode($a=false)
		{
			if (is_null($a)) return 'null';
			if ($a === false) return 'false';
			if ($a === true) return 'true';
			if (is_scalar($a))
			{
				if (is_float($a))
				{
					return floatval(str_replace(',', '.', strval($a)));
				}
				if (is_string($a))
				{
					static $jsonReplaces = array(array('\\', '/', "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
					return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
				}
				else
				{
					return $a;
				}
			}
	
			$isList = false;
			$keys = array_keys($a);
			$max_length = count($a)-1; 
			if(($keys[0]==0) && ($keys[$max_length] === $max_length))
			{
				$isList = true;
				for($i=0; $i<count($keys); $i++)
				{
					if($i!=$keys[$i])
					{
						$isList = false;
						break; 
					} 
				} 
			} 

			$result = array();
			if($isList)
			{
				foreach ($a as $v)
					$result[] = json_encode($v);
				return '['.join(',', $result).']';
			}
			else
			{
				foreach ($a as $k => $v)
					$result[] = json_encode($k).':'.json_encode($v);
				return '{'.join(',', $result).'}';
			}
		}
	}


	if(!function_exists('json_decode'))
	{
		function json_decode($json)
		{
			$json = ($json=="[]") ? "{}" : $json;
			$json = str_replace("[{","{{",$json);
			$json = str_replace("}]","}}",$json);
			$json = str_replace("[","{",$json);
			$json = str_replace("]","}",$json);
			$json = ($json=="") ? "{}" : $json;

			$comment = false;
			$out = '$x=';

			for ($i=0; $i<strlen($json); $i++)
			{
				if (!$comment)
				{
					if ($json[$i] == '{')
						$out .= ' array(';
					else if ($json[$i] == '}')
						$out .= ')';
					else if ($json[$i] == ':')
						$out .= '=>';
					else
						$out .= $json[$i];
				} 
				else
				{
					$out .= $json[$i]; 
				}
	
				if ($json[$i] == '"')
					$comment = !$comment;
			} 
			eval($out . ';'); 
			return $x; 
		}  
	}
?>