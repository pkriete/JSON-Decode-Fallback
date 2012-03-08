<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * The code in this file is heavily based on PHP 5.3.3's native
 * C implementation [ext/json/JSON_parser.c].
 */

/**
 * This class is meant as a fallback. The native json_decode is
 * much more performant. You are highly encouraged to check for
 * json_decode before including this file. The javascript driver
 * does this automatically.
 */
class Json {
	
	protected $decode_str = '';
	protected $decode_val;
	protected $decode_top;
	protected $decode_stack;		// main symbol stack
	protected $decode_buffer;		// array/object nesting stack
	
	protected $str_buf;
	protected $key_buf;
	
	protected $type;
	protected $state;
	
	const TYPE_NULL		= 0;
	const TYPE_LONG		= 1;
	const TYPE_DOUBLE	= 2;
	const TYPE_BOOL		= 3;
	const TYPE_ARRAY	= 4;
	const TYPE_OBJECT	= 5;
	const TYPE_STRING	= 6;
	
	// --------------------------------------------------------------------

	/**
	 * Decode
	 *
	 * @access	public
	 * @param	string	json string
	 * @param	bool	use assoc arrays instead of objects
	 * @param	int		max nesting depth
	 * @return	mixed	decoded json
	 */
	function decode($str/*, $assoc = FALSE, $depth = 512*/)
	{
		$str = trim($str);
		
		// anything to parse?
		if ($str === '')
		{
			return NULL;
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
		
		$this->type		= -1;
		$this->state	= 'GO';
		$this->str_buf	= '';
		$this->key_buf	= '';
		
		unset($str);
		
		return $this->_run();
	}
	
	// --------------------------------------------------------------------

	/**
	 * Run Decode
	 *
	 * @access	protected
	 * @return	mixed	decoded json
	 */
	protected function _run()
	{
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
		
		$classes = array(
			'C_SPACE' => 0,  /* space */
			'C_WHITE' => 1,  /* other whitespace */
			'C_LCURB' => 2,  /* {  */
			'C_RCURB' => 3,  /* } */
			'C_LSQRB' => 4,  /* [ */
			'C_RSQRB' => 5,  /* ] */
			'C_COLON' => 6,  /* : */
			'C_COMMA' => 7,  /* ', */
			'C_QUOTE' => 8,  /* " */
			'C_BACKS' => 9,  /* \ */
			'C_SLASH' => 10,  /* / */
			'C_PLUS'  => 11,  /* + */
			'C_MINUS' => 12,  /* - */
			'C_POINT' => 13,  /* . */
			'C_ZERO'  => 14,  /* 0 */
			'C_DIGIT' => 15,  /* 123456789 */
			'C_LOW_A' => 16,  /* a */
			'C_LOW_B' => 17,  /* b */
			'C_LOW_C' => 18,  /* c */
			'C_LOW_D' => 19,  /* d */
			'C_LOW_E' => 20,  /* e */
			'C_LOW_F' => 21,  /* f */
			'C_LOW_L' => 22,  /* l */
			'C_LOW_N' => 23,  /* n */
			'C_LOW_R' => 24,  /* r */
			'C_LOW_S' => 25,  /* s */
			'C_LOW_T' => 26,  /* t */
			'C_LOW_U' => 27,  /* u */
			'C_ABCDF' => 28,  /* ABCDF */
			'C_E'     => 29,      /* E */
			'C_ETC'   => 30,    /* everything else */
			'NR_CLASSES' => 31
		);
		
		// Hat tip to the PHP folks for the state and transition tables.
		// Turning the transition table into a multidimensional array was
		// reallly slow, so I converted it to strings. It's case sensitive
		// as we need ~40 states and a-z 0-9 doesn't quite fill it.
		
		// All the originally negative numbers are now positive.
		// Everything else is as mapped in $states.
		
		$states = array(
			'0' => 'GO',  /* start    */	'a' => 'OK',  /* ok       */	'b' => 'OB',  /* object   */
			'c' => 'KE',  /* key      */	'd' => 'CO',  /* colon    */	'e' => 'VA',  /* value    */
			'f' => 'AR',  /* array    */	'g' => 'ST',  /* string   */	'h' => 'ES',  /* escape   */
			'i' => 'U1',  /* u1       */	'j' => 'U2',  /* u2       */	'k' => 'U3',  /* u3       */
			'l' => 'U4',  /* u4       */	'm' => 'MI',  /* minus    */	'n' => 'ZE',  /* zero     */
			'o' => 'IN',  /* integer  */	'p' => 'FR',  /* fraction */	'q' => 'E1',  /* e        */
			'r' => 'E2',  /* ex       */	's' => 'E3',  /* exp      */	't' => 'T1',  /* tr       */
			'u' => 'T2',  /* tru      */	'v' => 'T3',  /* true     */	'w' => 'F1',  /* fa       */
			'x' => 'F2',  /* fal      */	'y' => 'F3',  /* fals     */	'z' => 'F4',  /* false    */
			'A' => 'N1',  /* nu       */	'B' => 'N2',  /* nul      */	'C' => 'N3',  /* null     */
			'D' => 'NR_STATES'
		);
		
		$state_names = array_flip($states);
		
		/*
		 * 0 == GO
		 * 1 == __
		 * All other numbers are interpreted as in $actions below
		 * All letters are interpreted as in $states above
		 */
		$transition_table = array(
			'0' => '00615111g1111111111111111111111',
			'a' => 'aa18171311111111111111111111111',
			'b' => 'bb191111g1111111111111111111111',
			'c' => 'cc111111g1111111111111111111111',
			'd' => 'dd11112111111111111111111111111',
			'e' => 'ee615111g111m1no11111w1A11t1111',
			'f' => 'ff615711g111m1no11111w1A11t1111',
			'g' => 'g1gggggg4hggggggggggggggggggggg',
			'h' => '11111111ggg111111g111g1gg1gi111',
			'i' => '11111111111111jjjjjjjj111111jj1',
			'j' => '11111111111111kkkkkkkk111111kk1',
			'k' => '11111111111111llllllll111111ll1',
			'l' => '11111111111111gggggggg111111gg1',
			'm' => '11111111111111no111111111111111',
			'n' => 'aa18171311111p111111q11111111q1',
			'o' => 'aa18171311111poo1111q11111111q1',
			'p' => 'aa181713111111pp1111q11111111q1',
			'q' => '11111111111rr1ss111111111111111',
			'r' => '11111111111111ss111111111111111',
			's' => 'aa181713111111ss111111111111111',
			't' => '111111111111111111111111u111111',
			'u' => '111111111111111111111111111v111',
			'v' => '11111111111111111111a1111111111',
			'w' => '1111111111111111x11111111111111',
			'x' => '1111111111111111111111y11111111',
			'y' => '1111111111111111111111111z11111',
			'z' => '11111111111111111111a1111111111',
			'A' => '111111111111111111111111111B111',
			'B' => '1111111111111111111111C11111111',
			'C' => '1111111111111111111111a11111111'
		);
		
		$ctrl_chars = array(
			'b' => "\010",
			't' => "\011",
			'n' => "\012",
			'f' => "\014",
			'r' => "\015"
		);
		
		$actions = array(
			2 => 'colon',
			3 => 'comma',
			4 => 'quote',
			5 => 'collection_open',
			6 => 'collection_open',
			7 => 'collection_close',
			8 => 'collection_close',
			9 => 'empty_close_curly'
		);
		
		$length = strlen($this->decode_str);
		
		$utf16 = 0;
		
		for ($i = 0; $i < $length; $i++)
		{
			$char = $this->decode_str[$i];
			$chr = ord($char);

			$char_cls = ($chr >= 128) ? 'C_ETC' : $ascii[$chr];

			if ($char_cls == '__' OR $this->state == '__')
			{
				return NULL;
			}
			
			$new_state = $transition_table[ $state_names[$this->state] ][ $classes[$char_cls] ];

			if (is_numeric($new_state))
			{
				if (isset($actions[$new_state]))
				{
					$func = '_act_'.$actions[$new_state];
					
					if (FALSE !== $this->$func($new_state))
					{
						continue;
					}
				}
				
				return NULL;
			}
			
			$new_state = $states[$new_state];
			
			if ($this->type == self::TYPE_STRING)
			{
				if ($new_state == 'ST' && $this->state != 'U4')
				{
					if ($this->state == 'ES' && isset($ctrl_chars[$char]))
					{
						$char = $ctrl_chars[$char];
					}

					$this->str_buf .= $char;
				}
				elseif ($new_state == 'U2')
				{
					// first hex digit in unicode char (i.e. \uFFFF)
					$utf16 = hexdec($char) << 12;
				}
				elseif ($new_state == 'U3')
				{
					$utf16 = $utf16 | hexdec($char) << 8;
				}
				elseif ($new_state == 'U4')
				{
					$utf16 = $utf16 | hexdec($char) << 4;
				}
				elseif ($new_state == 'ST' && $this->state == 'U4')
				{
					$utf16 = $utf16 | hexdec($char);

					// two byte char
					if ($utf16 == (0x07FF & $utf16))
					{
						$this->str_buf .= chr(0xC0 | (($utf16 >> 6) & 0x1F))
										. chr(0x80 | ($utf16 & 0x3F));
					}
					// three byte char
					elseif ($utf16 == (0xFFFF & $utf16))
					{
						$this->str_buf .= chr(0xE0 | (($utf16 >> 12) & 0x0F))
		                     			. chr(0x80 | (($utf16 >>  6) & 0x3F))
										. chr(0x80 | ($utf16 & 0x3F));
					}
				}
			}
			elseif ($this->type < self::TYPE_LONG && ($char_cls == 'C_DIGIT' OR $char_cls == 'C_ZERO'))
			{
				$this->type = self::TYPE_LONG;
				$this->str_buf .= $char;
			}
			elseif (($this->type == self::TYPE_LONG && $new_state == 'E1') ||
					($this->type < self::TYPE_DOUBLE && $char_cls == 'C_POINT'))
			{
				$this->type = self::TYPE_DOUBLE;
				$this->str_buf .= $char;
			}
			elseif ($this->type < self::TYPE_STRING && $char_cls == 'C_QUOTE')
			{
				$this->type = self::TYPE_STRING;
			}
			elseif ($this->type < self::TYPE_BOOL && $new_state == 'OK' && ($this->state == 'T3' || $this->state == 'F4'))
			{
				$this->type = self::TYPE_BOOL;
			}
			elseif ($this->type < self::TYPE_NULL && $new_state == 'OK' && $this->state == 'N3')
			{
				$this->type = self::TYPE_NULL;
			}
			elseif ($this->type != self::TYPE_STRING && $char_cls > 1 /* C_WHITE */)
			{
				$this->str_buf .= $char;
			}
			
			$this->state = $new_state;
		}
		
		if ($this->state == 'OK' && $this->_pop('DONE'))
		{
			return $this->decode_val;
		}
		
		return $this->_var_from_buffer();
	}
	
	// --------------------------------------------------------------------

	/**
	 * Handle colons
	 *
	 * @access	protected
	 * @return	bool	success
	 */
	protected function _act_colon()
	{
		if ($this->_pop('KEY') && $this->_push('OBJECT'))
		{
			return $this->_state('VA');
		}
		
		return FALSE;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Handle commas
	 *
	 * @access	protected
	 * @return	bool	success
	 */
	protected function _act_comma()
	{
		$top = $this->_peek();
		$val = NULL;
		
		if ($this->type != -1 && ($top == 'OBJECT' || $top == 'ARRAY'))
		{
			$val = $this->_var_from_buffer();
		}
		
		if ($top == 'OBJECT')
		{
			// flip the stack to key we don't _push and _pop here
			// since we know $top and the stack size is unchanged
			
			array_pop($this->decode_stack);
			$this->decode_stack[] = 'KEY';
			
			if ($this->type != -1)
			{
				// @todo check here for decode() param 2
				if ( ! isset($this->decode_buffer[$this->decode_top]))
				{
					$this->decode_buffer[$this->decode_top] = new stdClass();
				}
				
				$obj =& $this->decode_buffer[$this->decode_top];
				$obj->{$this->key_buf} = $val;
			}
			
			return $this->_state('KE', -1);
		}
		elseif ($top == 'ARRAY')
		{
			if ( ! isset($this->decode_buffer[$this->decode_top]))
			{
				$this->decode_buffer[$this->decode_top] = array();
			}
			
			$arr =& $this->decode_buffer[$this->decode_top];
			$arr[] = $val;
			
			return $this->_state('VA', -1);
		}
		
		return FALSE;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Handle quotes
	 *
	 * @access	protected
	 * @return	bool	success
	 */
	protected function _act_quote()
	{
		$top = $this->_peek();
		
		switch ($top)
		{
			case 'KEY':
				$this->key_buf = $this->str_buf;
				$this->str_buf = '';
				return $this->_state('CO', -1);
			case 'ARRAY':
			case 'OBJECT':
				return $this->_state('OK');
			case 'DONE':
				if ($this->type == 6)	// STRING
				{
					$this->decode_val = $this->str_buf;
					$this->str_buf = '';
					
					return $this->_state('OK');
				}
			default:
				return FALSE;
		}
	}
	
	// --------------------------------------------------------------------

	/**
	 * Handle {'s and ['s
	 *
	 * @access	protected
	 * @return	bool	success
	 */
	protected function _act_collection_open($which)
	{
		// 5 == [
		// 6 == {
		
		$push = 'KEY';
		$state = 'OB';
		
		if ($which == 5)
		{
			$push = 'ARRAY';
			$state = 'AR';
		}
				
		if ( ! $this->_push($push))
		{
			return FALSE;
		}

		// If it's an object all of the references in here
		// are superfluous, but it doesn't hurt and we can
		// avoid separate methods for objects and arrays.
		if ($this->decode_top > 0)
		{
			$el = ($which == 5) ? array() : new stdClass();

			$this->decode_buffer[$this->decode_top] =& $el;

			if ($this->decode_top == 1)
			{
				$this->decode_val =& $el;
			}
			else
			{
				$prev =& $this->decode_buffer[$this->decode_top - 1];

				if (is_array($prev))
				{
					$prev[] =& $el;
				}
				else
				{
					$prev->{$this->key_buf} =& $el;
				}

				$this->type = -1;
			}
		}

		return $this->_state($state);
	}
	
	// --------------------------------------------------------------------

	/**
	 * Handle }'s and ]'s
	 *
	 * @access	protected
	 * @return	bool	success
	 */
	protected function _act_collection_close($which)
	{
		$expect = ($which == 7) ? 'ARRAY' : 'OBJECT';

		$top = $this->_peek();

		if ($top == $expect && $this->type != -1)
		{
			$val = $this->_var_from_buffer();
			$el =& $this->decode_buffer[$this->decode_top];

			if ($expect == 'ARRAY')
			{
				$el[] = $val;
			}
			else
			{
				$el->{$this->key_buf} = $val;
				$this->key_buf = '';
			}

			$this->type = -1;
		}

		if ($this->_pop($expect))
		{
			return $this->_state('OK');
		}

		return FALSE;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Handle blank objects
	 *
	 * @access	protected
	 * @return	bool	success
	 */
	protected function _act_empty_close_curly()
	{
		if ($this->_pop('KEY'))
		{
			return $this->_state('OK');
		}
		
		return FALSE;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Takes the current string buffer, casts it to a PHP variable
	 * of the correct type, clears the buffer, and returns the var.
	 *
	 * @access	protected
	 * @return	mixed	typecast var
	 */
	protected function _var_from_buffer()
	{
		$str = $this->str_buf;
		$this->str_buf = '';
		
		switch ($this->type)
		{
			case self::TYPE_LONG:
			case self::TYPE_DOUBLE:
				return (double) $str;
			case self::TYPE_BOOL:
				return (bool) $str;
			case self::TYPE_STRING:
				return $str;
			default:
				return (unset) $str;
		}
	}
	
	// --------------------------------------------------------------------

	/**
	 * Change current state
	 *
	 * Convenvience method to allow for easier setting of state
	 * and type, which frequently change together.
	 *
	 * @access	protected
	 * @return	bool	TRUE
	 */
	protected function _state($state, $type = FALSE)
	{
		if ($type !== FALSE)
		{
			$this->type = $type;
		}
		
		$this->state = $state;
		return TRUE;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Peek at the top of the stack
	 *
	 * @access	protected
	 * @return	mixed	top of the stack
	 */
	protected function _peek()
	{
		return $this->decode_stack[$this->decode_top];
	}
	
	// --------------------------------------------------------------------

	/**
	 * Push a state onto the stack
	 *
	 * @access	protected
	 * @return	bool	true
	 */
	protected function _push($val)
	{
		// @todo check stack size
		$this->decode_stack[] = $val;
		$this->decode_top++;
		
		return TRUE;
	}
	
	// --------------------------------------------------------------------

	/**
	 * Pop a state off the stack
	 *
	 * @access	protected
	 * @return	mixed	top of the stack
	 */
	protected function _pop($expected)
	{
		$old_state = array_pop($this->decode_stack);
		$this->decode_top--;
		
		return ($old_state == $expected);
	}
}

/* End of file  */
/* Location:  */