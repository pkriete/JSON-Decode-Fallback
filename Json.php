<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Json {
	
	protected $decode_str = '';
	protected $decode_val;
	protected $decode_stack;		// stack of ARRAY, OBJECT, KEY, DONE
	protected $decode_buffer;
	protected $decode_top;
	
	function decode($str/*, $assoc = FALSE, $depth = 512*/)
	{
		$str = trim($str);
		
		// anything to parse?
		if ($str === '')
		{
			return NULL;
		}
		
		// Native parser? Easy peasy
		if (function_exists('json_decode'))
		{
		//	return json_decode($str);
		}
		
		// Check the basics data types for quick decoding
		switch($str)
		{
			case 'false':	return FALSE;
			case 'true':	return TRUE;
			case 'null':	return NULL;
		}

		// Dealing with something more complex,
		// make sure it starts out valid
		$first_char = $str[0];
		
		if ($first_char != '[' AND $first_char != '{' AND $first_char != '"')
		{
			if ( ! is_numeric($first_char))
			{
				// baaad
				return NULL;
			}
		}
		
		// Reset everything!
		
		$this->decode_buffer = array();
		$this->decode_stack = array('DONE');
		$this->decode_val = NULL;
		$this->decode_str = $str;
		$this->decode_top = 0;
		
		unset($str);
		$res = $this->_tokenize();

		return $res;
	}
	
	function _tokenize()
	{
		// Ok, off we go. This is heavily
		// based on PHP 5.3.3's native C implementation.
		// Kudos to them for the clever method to reduce
		// the size of the state transition table.
		
		$classes = array_flip(array(
			'C_SPACE',  /* space */
			'C_WHITE',  /* other whitespace */
			'C_LCURB',  /* {  */
			'C_RCURB',  /* } */
			'C_LSQRB',  /* [ */
			'C_RSQRB',  /* ] */
			'C_COLON',  /* : */
			'C_COMMA',  /* ', */
			'C_QUOTE',  /* " */
			'C_BACKS',  /* \ */
			'C_SLASH',  /* / */
			'C_PLUS',   /* + */
			'C_MINUS',  /* - */
			'C_POINT',  /* . */
			'C_ZERO ',  /* 0 */
			'C_DIGIT',  /* 123456789 */
			'C_LOW_A',  /* a */
			'C_LOW_B',  /* b */
			'C_LOW_C',  /* c */
			'C_LOW_D',  /* d */
			'C_LOW_E',  /* e */
			'C_LOW_F',  /* f */
			'C_LOW_L',  /* l */
			'C_LOW_N',  /* n */
			'C_LOW_R',  /* r */
			'C_LOW_S',  /* s */
			'C_LOW_T',  /* t */
			'C_LOW_U',  /* u */
			'C_ABCDF',  /* ABCDF */
			'C_E',      /* E */
			'C_ETC',    /* everything else */
			'NR_CLASSES'
		));
		
		$states = array_flip(array(
			'GO',  /* start    */
			'OK',  /* ok       */
			'OB',  /* object   */
			'KE',  /* key      */
			'CO',  /* colon    */
			'VA',  /* value    */
			'AR',  /* array    */
			'ST',  /* string   */
			'ES',  /* escape   */
			'U1',  /* u1       */
			'U2',  /* u2       */
			'U3',  /* u3       */
			'U4',  /* u4       */
			'MI',  /* minus    */
			'ZE',  /* zero     */
			'IN',  /* integer  */
			'FR',  /* fraction */
			'E1',  /* e        */
			'E2',  /* ex       */
			'E3',  /* exp      */
			'T1',  /* tr       */
			'T2',  /* tru      */
			'T3',  /* true     */
			'F1',  /* fa       */
			'F2',  /* fal      */
			'F3',  /* fals     */
			'F4',  /* false    */
			'N1',  /* nu       */
			'N2',  /* nul      */
			'N3',  /* null     */
			'NR_STATES'
		));
		
		$ascii = array(
			'__',		'__',		'__',		'__',		'__',		'__',		'__',		'__',
			'__',		'C_WHITE',	'C_WHITE',	'__',		'__',		'C_WHITE',	'__',		'__',
			'__',		'__',		'__',		'__',		'__',		'__',		'__',		'__',
			'__',		'__',		'__',		'__',		'__',		'__',		'__',		'__',

			'C_SPACE',	'C_ETC',	'C_QUOTE',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',
			'C_ETC',	'C_ETC',	'C_ETC',	'C_PLUS',	'C_COMMA',	'C_MINUS',	'C_POINT',	'C_SLASH',
			'C_ZERO',	'C_DIGIT',	'C_DIGIT',	'C_DIGIT',	'C_DIGIT',	'C_DIGIT',	'C_DIGIT',	'C_DIGIT',
			'C_DIGIT',	'C_DIGIT',	'C_COLON',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',

			'C_ETC',	'C_ABCDF',	'C_ABCDF',	'C_ABCDF',	'C_ABCDF',	'C_E',		'C_ABCDF',	'C_ETC',
			'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',
			'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',
			'C_ETC',	'C_ETC',	'C_ETC',	'C_LSQRB',	'C_BACKS',	'C_RSQRB',	'C_ETC',	'C_ETC',
                    	
			'C_ETC',	'C_LOW_A',	'C_LOW_B',	'C_LOW_C',	'C_LOW_D',	'C_LOW_E',	'C_LOW_F',	'C_ETC',
			'C_ETC',	'C_ETC',	'C_ETC',	'C_ETC',	'C_LOW_L',	'C_ETC',	'C_LOW_N',	'C_ETC',
			'C_ETC',	'C_ETC',	'C_LOW_R',	'C_LOW_S',	'C_LOW_T',	'C_LOW_U',	'C_ETC',	'C_ETC',
			'C_ETC',	'C_ETC',	'C_ETC',	'C_LCURB',	'C_ETC',	'C_RCURB',	'C_ETC',	'C_ETC'
		);
		
		$transition_table = array(

		/*						 white																	1-9																ABCDF	  etc
							space  |   {     }   [     ]    :   ','  "    \     /    +    -    .   0    |     a    b    c    d    e    f    l    n    r    s    t    u   |    E    | */
		/*start  GO*/ array('GO','GO', -6 ,'__', -5 ,'__','__','__','ST','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__'),
		/*ok     OK*/ array('OK','OK','__', -8 ,'__', -7 ,'__', -3 ,'__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__'),
		/*object OB*/ array('OB','OB','__', -9 ,'__','__','__','__','ST','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__'),
		/*key    KE*/ array('KE','KE','__','__','__','__','__','__','ST','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__'),
		/*colon  CO*/ array('CO','CO','__','__','__','__', -2 ,'__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__'),
		/*value  VA*/ array('VA','VA', -6 ,'__', -5 ,'__','__','__','ST','__','__','__','MI','__','ZE','IN','__','__','__','__','__','F1','__','N1','__','__','T1','__','__','__','__'),
		/*array  AR*/ array('AR','AR', -6 ,'__', -5 , -7 ,'__','__','ST','__','__','__','MI','__','ZE','IN','__','__','__','__','__','F1','__','N1','__','__','T1','__','__','__','__'),
		/*string ST*/ array('ST','__','ST','ST','ST','ST','ST','ST', -4 ,'ES','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST','ST'),
		/*escape ES*/ array('__','__','__','__','__','__','__','__','ST','ST','ST','__','__','__','__','__','__','ST','__','__','__','ST','__','ST','ST','__','ST','U1','__','__','__'),
		/*u1     U1*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','U2','U2','U2','U2','U2','U2','U2','U2','__','__','__','__','__','__','U2','U2','__'),
		/*u2     U2*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','U3','U3','U3','U3','U3','U3','U3','U3','__','__','__','__','__','__','U3','U3','__'),
		/*u3     U3*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','U4','U4','U4','U4','U4','U4','U4','U4','__','__','__','__','__','__','U4','U4','__'),
		/*u4     U4*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','ST','ST','ST','ST','ST','ST','ST','ST','__','__','__','__','__','__','ST','ST','__'),
		/*minus  MI*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','ZE','IN','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__'),
		/*zero   ZE*/ array('OK','OK','__', -8 ,'__', -7 ,'__', -3 ,'__','__','__','__','__','FR','__','__','__','__','__','__','E1','__','__','__','__','__','__','__','__','E1','__'),
		/*int    IN*/ array('OK','OK','__', -8 ,'__', -7 ,'__', -3 ,'__','__','__','__','__','FR','IN','IN','__','__','__','__','E1','__','__','__','__','__','__','__','__','E1','__'),
		/*frac   FR*/ array('OK','OK','__', -8 ,'__', -7 ,'__', -3 ,'__','__','__','__','__','__','FR','FR','__','__','__','__','E1','__','__','__','__','__','__','__','__','E1','__'),
		/*e      E1*/ array('__','__','__','__','__','__','__','__','__','__','__','E2','E2','__','E3','E3','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__'),
		/*ex     E2*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','E3','E3','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__'),
		/*exp    E3*/ array('OK','OK','__', -8 ,'__', -7 ,'__', -3 ,'__','__','__','__','__','__','E3','E3','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__'),
		/*tr     T1*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','T2','__','__','__','__','__','__'),
		/*tru    T2*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','T3','__','__','__'),
		/*true   T3*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','OK','__','__','__','__','__','__','__','__','__','__'),
		/*fa     F1*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','F2','__','__','__','__','__','__','__','__','__','__','__','__','__','__'),
		/*fal    F2*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','F3','__','__','__','__','__','__','__','__'),
		/*fals   F3*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','F4','__','__','__','__','__'),
		/*false  F4*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','OK','__','__','__','__','__','__','__','__','__','__'),
		/*nu     N1*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','N2','__','__','__'),
		/*nul    N2*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','N3','__','__','__','__','__','__','__','__'),
		/*null   N3*/ array('__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','__','OK','__','__','__','__','__','__','__','__')
		
		);
		
		
		$ctrl_chars = array_map('chr', array(
			'b' => 8,
			't' => 9,
			'n' => 10,
			'f' => 12,
			'r' => 13
		));
		
		$t = array_flip(array(
			'NULL',
			'LONG',
			'DOUBLE',
			'BOOL',
			'ARRAY',
			'OBJECT',
			'STRING'
		));
		
		$actions = array(
			-2 => 'colon',
			-3 => 'comma',
			-4 => 'quote',
			-5 => 'open_square',
			-6 => 'open_curly',
			-7 => 'close_square',
			-8 => 'close_curly',
			-9 => 'empty_close_curly'
		);
		
		$length		= strlen($this->decode_str);
		
		$state		= 'GO';
		$type		= -1;
		$str_buf	= '';
		$key_buf	= '';
		
		for ($i = 0; $i < $length; $i++)
		{
			$char = $this->decode_str[$i];
			$chr = ord($char);
			
			$char_cls = ($chr >= 128) ? 'C_ETC' : $ascii[$chr];
			
			if ($char_cls == '__' OR $state == '__')
			{
				return NULL;
			}
			
			$state = $states[$state];
			$char_cls_str = $classes[$char_cls];
			
			$new_state = $transition_table[$state][$char_cls_str];
			
			if ($new_state < 0)
			{
				if ( ! isset($actions[$new_state]))
				{
					return NULL;
				}
				
				$func = '_act_'.$actions[$new_state];
				
				$change_vars = $this->$func($state, $type, $str_buf, $key_buf);
				
				if ($change_vars === FALSE)
				{
					return NULL;
				}
				
				if (is_array($change_vars))
				{
					// can contain any of the parameters to $func
					extract($change_vars);
				}				
			}
			else
			{
				if ($type == $t["STRING"])
				{
					if ($new_state == 'ST' && $state != 'U4')
					{
						if ($state == 'ES' && isset($ctrl_chars[$char]))
						{
							$str_buf .= $ctrl_chars[$char];
						}
						else
						{
							$str_buf .= $char;
						}
					}
					elseif ($new_state == 'U2' OR $new_state == 'U3' OR $new_state == 'U4')
					{
						die('@todo utf16 support');
					}
				}
				elseif ($type < $t['LONG'] && ($char_cls == 'C_DIGIT' OR $char_cls == 'C_ZERO'))
				{
					$type = $t['LONG'];
					$str_buf .= $char;
				}
				elseif ($type == $t['LONG'] && $new_state == 'E1')
				{
					$type = $t['DOUBLE'];
					$str_buf .= $char;
				}
				elseif ($type < $t['DOUBLE'] && $char_cls == 'C_POINT')
				{
					$type = $t['DOUBLE'];
					$str_buf .= $char;
				}
				elseif ($type < $t['STRING'] && $char_cls = 'C_QUOTE')
				{
					$type = $t['STRING'];
				}
				elseif ($type < $t['BOOL'] && $new_state == 'OK' && ($state == 'T3' || $state == 'F4'))
				{
					$type = $t['BOOL'];
				}
				elseif ($type < $t['NULL'] && $state == 'N3' && $new_state == 'OK')
				{
					$type = $t['NULL'];
				}
				elseif ($type != $t['STRING'] && $char_cls > $classes['C_WHITE'])
				{
					$str_buf .= $char;
				}
				
				$state = $new_state;
			}
		}
				
		if ($state == 'OK' && $this->_pop('DONE'))
		{
			return $this->decode_val;
		}
		
		return $this->_type_cast($str_buf, $type);
	}
	
	function _act_colon($state, $type, $str_buf, $key_buf)
	{
		if ($this->_pop('KEY') && $this->_push('OBJECT'))
		{
			return array('state' => 'VA');
		}
		
		return FALSE;
	}
	
	function _act_comma($state, $type, $str_buf, $key_buf)
	{
		$top = $this->_peek();
		$val = NULL;
		
		$ret = array(
			'type' => -1
		);
				
		if ($type != -1 && ($top == 'OBJECT' || $top == 'ARRAY'))
		{
			$val = $this->_type_cast($str_buf, $type);
			$ret['str_buf'] = '';
		}
		
		
		if ($top == 'OBJECT')
		{
			// pop and push, but we know they'll
			// both be successful since we peeked
			array_pop($this->decode_stack);	// == $top
			$this->decode_stack[] = 'KEY';
			
			if ($type != -1)
			{
				// @todo check here for decode() param 2
				if ( ! isset($this->decode_buffer[$this->decode_top]))
				{
					$this->decode_buffer[$this->decode_top] = new stdClass();
				}
				
				$obj =& $this->decode_buffer[$this->decode_top];
				$obj->$key_buf = $val;
			}
			
			$ret['state'] = 'KE';
		}
		elseif ($top == 'ARRAY')
		{
			if ( ! isset($this->decode_buffer[$this->decode_top]))
			{
				$this->decode_buffer[$this->decode_top] = array();
			}
			
			$arr =& $this->decode_buffer[$this->decode_top];
			$arr[] = $val;
			
			$ret['state'] = 'VA';
		}
		else
		{
			return FALSE;
		}
		
		return $ret;
	}
	
	function _act_quote($state, $type, &$str_buf, &$key_buf)
	{
		$top = $this->_peek();

		switch ($top)
		{
			case 'KEY':
				$key_buf = $str_buf;
				return array(
					'type' => -1,
					'state' => 'CO',
					'str_buf' => ''
				);
			case 'ARRAY':
			case 'OBJECT':
				return array('state' => 'OK');
			case 'DONE':
				if ($type == 6)	// 6 == $t['STRING']
				{
					$this->decode_val = $str_buf;
					$str_buf = '';
					
					return array('state' => 'OK');
				}
			default:
				return FALSE;
		}
	}
	
	function _act_open_square($state, $type, $str_buf, $key_buf)
	{
		// @todo check stack size
		if ( ! $this->_push('ARRAY'))
		{
			return FALSE;
		}
			
		$ret = array(
			'state' => 'AR'
		);
				
		if ($this->decode_top > 0)
		{			
			$arr = array();

			$this->decode_buffer[$this->decode_top] =& $arr;
			
			if ($this->decode_top == 1)
			{
				$this->decode_val =& $arr;
			}
									
			if ($this->decode_top > 1)
			{
				$prev =& $this->decode_buffer[$this->decode_top - 1];

				if (is_array($prev))
				{
					$prev[] =& $arr;
				}
				else
				{
					$prev->$key_buf =& $arr;
				}

				$ret['type'] = -1;
			}
		}
				
		return $ret;
	}
	
	function _act_open_curly($state, $type, $str_buf, $key_buf)
	{
		// @todo check stack size
		if ( ! $this->_push('KEY'))
		{
			return FALSE;
		}
				
		$ret = array(
			'state' => 'OB'
		);
		
		if ($this->decode_top > 0)
		{
			$obj = new stdClass();
			$this->decode_buffer[$this->decode_top] = $obj;
			
			if ($this->decode_top == 1)
			{
				$this->decode_val = $obj;
			}
			
			if ($this->decode_top > 1)
			{
				$prev =& $this->decode_buffer[$this->decode_top - 1];

				if (is_array($prev))
				{
					$prev[] = $obj;
				}
				else
				{
					$prev->$key_buf = $obj;
				}

				$ret['type'] = -1;
			}
		}
		
		return $ret;
	}
	
	function _act_close_square($state, $type, $str_buf, $key_buf)
	{
		$top = $this->_peek();
		
		$ret = array();
				
		if ($type != -1 && $top == 'ARRAY')
		{
			$val = $this->_type_cast($str_buf, $type);
			$arr =& $this->decode_buffer[$this->decode_top];
			$arr[] = $val;
			
			$this->decode_buffer[$this->decode_top] = $arr;
			
			$ret = array(
				'type' => -1,
				'str_buf' => ''
			);
		}
		
		if ($this->_pop('ARRAY'))
		{
			$ret['state'] = 'OK';
			return $ret;
		}
		
		return FALSE;
	}
	
	function _act_close_curly($state, $type, $str_buf, $key_buf)
	{
		$top = $this->_peek();
		
		$ret = array();
				
		if ($type != -1 && $top == 'OBJECT')
		{
			$val = $this->_type_cast($str_buf, $type);
			$obj =& $this->decode_buffer[$this->decode_top];
			
			$obj->$key_buf = $val;
			
			$ret = array(
				'type' => -1,
				'key_buf' => '',
				'str_buf' => ''
			);
		}
		
		if ($this->_pop('OBJECT'))
		{
			$ret['state'] = 'OK';
			return $ret;
		}
		
		return FALSE;
	}
	
	function _act_empty_close_curly($state, $type, $str_buf, $key_buf)
	{
		if ($this->_pop('KEY'))
		{
			return array('state' => 'OK');
		}
		
		return FALSE;
	}
	
	function _type_cast($str, $type)
	{
		$t = array_flip(array(
			'NULL',
			'LONG',
			'DOUBLE',
			'BOOL',
			'ARRAY',
			'OBJECT',
			'STRING'
		));
		
		switch ($type)
		{
			case $t['LONG']:
			case $t['DOUBLE']:
				return (double) $str;
			case $t['BOOL']:
				return (bool) $str;
			case $t['STRING']:
				return $str;
			default:
				return (unset) $str;
		}
	}
	
	function _peek()
	{
		return $this->decode_stack[$this->decode_top];
	}
	
	function _push($val)
	{
		$this->decode_stack[] = $val;
		$this->decode_top++;
		
		return TRUE;
	}
	
	function _pop($expected)
	{
		$old_state = array_pop($this->decode_stack);
		$this->decode_top--;
		
		return ($old_state == $expected);
	}
}

/* End of file */