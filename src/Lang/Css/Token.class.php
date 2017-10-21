<?php
/**
 * 继承自Fl_Token类
 */
Fl::loadClass ( 'Fl_Token' );
Fl::loadClass ( 'Fl_Css_Static' );
/**
 * 
 * CSS Tokenizar
 * @author welefen
 *
 */
class Fl_Css_Token extends Fl_Token {

	/**
	 * 
	 * validate tokens
	 * @var boolean
	 */
	public $validate = true;

	/**
	 * 
	 * prev token type
	 * @var string
	 */
	protected $preTokenType = '';

	/**
	 * 
	 * bracket number
	 * @var array
	 */
	protected $bracesNum = array (
		0, 
		0 
	);

	/**
	 * trim text
	 * @see Fl_Base::trim()
	 */
	public function trim($text = '') {
		$text = parent::trim ( $text );
		return $text;
	}

	/**
	 * 
	 * hex to ascii char
	 * @param string $str
	 */
	public static function hex2asc($str) {
		$str = trim ( $str );
		$len = strlen ( $str );
		for($i = 0; $i < $len; $i += 2) {
			$data .= chr ( hexdec ( substr ( $str, $i, 2 ) ) );
		}
		return $data;
	}

	/**
	 * 获取下一个TOKEN
	 * @see Fl_Token::getNextToken()
	 */
	public function getNextToken() {
		$token = parent::getNextToken ();
		if ($token || $token === false) {
			return $token;
		}
		$char = $this->text {$this->pos};
		switch ($char) {
			case "@" :
				$this->pendingNextChar = false;
				$value = rtrim ( $this->readWhile ( 'getAtToken' ) );
				$type = $this->getAtDetailType ( $value );
				break;
			case "{" :
				$type = $this->getStartBracesType ();
				$this->getNextChar ();
				break;
			case "}" :
				$type = $this->getEndBracesType ();
				$this->getNextChar ();
				break;
			case ":" :
				if ($this->preTokenType == FL_TOKEN_CSS_PROPERTY || $this->preTokenType == FL_TOKEN_TPL) {
					$this->getNextChar ();
					$type = $this->preTokenType = FL_TOKEN_CSS_COLON;
				}
				break;
			case ";" :
				if ($this->validate && $this->preTokenType === FL_TOKEN_CSS_PROPERTY) {
					$this->throwException ( "`;` can not after propery" );
				}
				$this->getNextChar ();
				$type = $this->preTokenType = FL_TOKEN_CSS_SEMICOLON;
				break;
		}
		if ($type) {
			return $this->getTokenInfo ( $type, isset ( $value ) ? $value : $char );
		}
		//specail tokens now only have [;color:red;], performance opti
		if ($char === '[') {
			$token = $this->getSpecialToken ();
			if ($token) {
				return $token;
			}
		}
		switch ($this->preTokenType) {
			case FL_TOKEN_CSS_BRACES_ONE_START :
			case FL_TOKEN_CSS_SEMICOLON :
				$result = $this->getPropertyToken ( $char );
				$this->preTokenType = FL_TOKEN_CSS_PROPERTY;
				if (! $this->checkProperty ( $result )) {
					$this->throwException ( "property `" . str_replace ( "\n", " ", $result ) . "` is not valid." );
				}
				return $this->getTokenInfo ( FL_TOKEN_CSS_PROPERTY, $result );
			case FL_TOKEN_CSS_COLON :
				#case FL_TOKEN_CSS_PROPERTY :
				$result = trim ( $this->getValueToken () );
				$this->preTokenType = FL_TOKEN_CSS_VALUE;
				return $this->getTokenInfo ( FL_TOKEN_CSS_VALUE, $result );
			default :
				//$result = trim ( $this->readWhile ( 'getSelectorToken' ) );
				$result = $this->getSelectorToken ( $char );
				if ($this->validate && $this->text {$this->pos} !== '{') {
					$this->throwException ( 'get Selector error `' . $result . '`' . $this->text );
				}
				$this->preTokenType = FL_TOKEN_CSS_SELECTOR;
				return $this->getTokenInfo ( FL_TOKEN_CSS_SELECTOR, $result );
		}
		$this->throwException ( 'uncaught char ' . $char );
	}

	/**
	 * 
	 * 检测属性名是否合法
	 * @param string $property
	 */
	public function checkProperty($property = "") {
		$pattern = "/\w/"; //如果属性名没有字母或者数字，则不合法
		return preg_match ( $pattern, $property );
	}

	/**
	 * 
	 * 获取@开头的token
	 * @param string $char
	 */
	public function getAtToken($char = '') {
		$comment = $this->getComment ( 'multi', false );
		if ($comment) {
			$this->pendingNextChar = true;
			return $comment;
		}
		if ($return = $this->getQuoteText ( $char, true )) {
			$this->pendingNextChar = true;
			return $return;
		}
		if ($this->text {$this->pos} === '{') {
			$this->pendingNextChar = true;
			return true;
		}
		if ($char === ';' || $this->text {$this->pos + 1} === '{') {
			return false;
		}
	}

	/**
	 * 
	 * 获取@的详细类型
	 */
	public function getAtDetailType($result = '') {
		return $this->preTokenType = Fl_Css_Static::getAtDetailType ( $result, $this );
	}

	/**
	 * 跳过评论
	 * @see Fl_Token::skipComment()
	 */
	public function skipComment() {
		while ( $this->text {$this->pos} === '/' ) {
			$comment = $this->getComment ( 'multi', true, true );
			if ($comment) {
				$this->commentBefore [] = $comment;
			} else {
				break;
			}
		}
	}

	/**
	 * 
	 * 获取左花括号的类型
	 */
	public function getStartBracesType() {
		switch ($this->preTokenType) {
			case FL_TOKEN_CSS_AT_MEDIA :
			case FL_TOKEN_CSS_AT_KEYFRAMES :
			case FL_TOKEN_CSS_AT_MOZILLA :
			case FL_TOKEN_CSS_AT_SUPPORT :
				$type = FL_TOKEN_CSS_BRACES_TWO_START;
				break;
			case FL_TOKEN_CSS_AT_FONTFACE :
			case FL_TOKEN_CSS_SELECTOR :
			case FL_TOKEN_CSS_AT_PAGE :
			case FL_TOKEN_CSS_AT_OTHER :
				$type = FL_TOKEN_CSS_BRACES_ONE_START;
				break;
		}
		if ($this->validate && ! $type) {
			$this->throwException ( 'get { error in ' );
		}
		$this->bracesNum [0] ++;
		$this->preTokenType = $type;
		return $type;
	}

	/**
	 * 
	 * 获取右花括号的类型
	 */
	public function getEndBracesType() {
		switch ($this->preTokenType) {
			case FL_TOKEN_CSS_VALUE :
			case FL_TOKEN_CSS_SEMICOLON :
			case FL_TOKEN_CSS_COLON :
			case FL_TOKEN_CSS_PROPERTY :
			case FL_TOKEN_CSS_BRACES_ONE_START :
				$type = FL_TOKEN_CSS_BRACES_ONE_END;
				break;
			default :
				$type = FL_TOKEN_CSS_BRACES_TWO_END;
		}
		$this->bracesNum [1] ++;
		return $this->preTokenType = $type;
	}

	/**
	 * 
	 * 获取属性名
	 */
	public function getPropertyToken($char = '') {
		$result = '';
		while ( $this->pos < $this->length ) {
			$result .= $this->getNextChar ();
			$nextChar = $this->text {$this->pos};
			//not break on next char is blank, `* display:none`
			if ($nextChar === ':' || $nextChar === '{' || $nextChar === ';' || $nextChar === '}') {
				break;
			}
		}
		return $result;
	}

	/**
	 * 
	 * 获取属性值
	 */
	public function getValueToken() {
		$return = '';
		$ldLen = strlen ( $this->ld );
		while ( true ) {
			$char = $this->text {$this->pos};
			if ($char === '"' || $char === "'") {
				//引号值里有;
				//a{font-family:"&#718;&#805;"}
				$matched = $this->getMatched ( $char, $char, true, false, false );
				if ($matched) {
					$return .= $matched;
				}
				continue;
			} elseif ($char === '/') {
				//值里有注释
				//@font-face {
				//  font-family: 'WebFont';
				//  src: url('myfont.eot?#') format('eot'),  /* IE6–8 */
				//       url('myfont.woff') format('woff'),  /* FF3.6+, IE9, Chrome6+, Saf5.1+*/
				//       url('myfont.ttf') format('truetype');  /* Saf3—5, Chrome4+, FF3.5, Opera 10+ */
				//}
				$comment = $this->getComment ( 'multi', false );
				if ($comment) {
					$return .= $comment;
					continue;
				}
			} elseif ($char === '(') {
				//expression or background（dataURI）value may be have `:` or `;`
				//@TODO:this condition check it not safe
				$re = stripslashes ( $return ); //去除转义符号\
				$re = preg_replace ( "/\/\*(.*?)\*\//", "", $re ); //移除注释
				if (preg_match ( "/expression\s*/is", $re ) || strtolower ( substr ( $this->text, $this->pos, 10 ) ) === "javascript" || strtolower ( substr ( $this->text, $this->pos, 8 ) ) === "vbscript") {
					$value = $this->getJsText ();
					$return .= $value;
				} else {
					$matched = $this->getMatched ( '(', ')' );
					if ($matched) {
						$return .= $matched;
					}
				}
				continue;
			}
			$nextChar = $this->text {$this->pos};
			//value may be has `/`, such as font: 13px/28px; 
			if ($nextChar === ';' || $nextChar === '}') {
				break;
			}
			$nextChar = $this->getNextChar ();
			if ($nextChar === false) {
				break;
			}
			$return .= $nextChar;
		}
		return $return;
	}

	/**
	 * 
	 * 获取expression里js部分的值
	 */
	public function getJsText() {
		$result = '';
		Fl::loadClass ( 'Fl_Js_Token' );
		while ( ($char = $this->getNextChar ()) !== false ) {
			$result .= $char;
			if ($char === ')') {
				$instance = new Fl_Js_Token ( $result );
				$instance->validate = false;
				$output = $instance->run ();
				if (($instance->validateData ['(']) === $instance->validateData [')']) {
					break;
				}
			}
		}
		return $result;
	}

	public function getSelectorToken($char = '') {
		$result = '';
		while ( $this->pos < $this->length ) {
			$char = $this->text {$this->pos};
			if ($char === '/') {
				if ($comment = $this->getComment ( 'multi', false )) {
					$result .= $comment;
					continue;
				}
				// 如果当前是/* 且没有找到 */ 则报错
				if ($this->text {$this->pos + 1} === '*') {
					$this->throwException ( "missing */ for /*" );
				}
			}
			if ($char === "'" || $char === '"') {
				if ($quote = $this->getQuoteText ( $char, true )) {
					$result .= $quote;
					continue;
				}
			}
			$token = $this->getTplToken ();
			if (! empty ( $token )) {
				$result .= $token;
				continue;
			}
			//for "div.red/***\/{}"
			if ($this->text {$this->pos} === "{") {
				break;
			}
			$result .= $char;
			// .result-folding-8 .r-results>div:nth-of-type(-n+{%$viewFlag.keepfolding%})  selector里含有模版语法，且定界符里含有{
			if ($this->text {$this->pos + 1} === '{' && $this->ld !== substr ( $this->text, $this->pos + 1, strlen ( $this->ld ) )) {
				$this->getNextChar ();
				break;
			} else {
				$this->getNextChar ();
			}
		}
		return rtrim ( $result );
	}

	/**
	 * 
	 * 获取一些特殊的token，如: chrome下的hack
	 */
	public function getSpecialToken() {
		foreach ( Fl_Css_Static::$specialTokens as $item ) {
			$result = $this->getMatched ( $item [0], $item [1], false, false, false );
			if ($result) {
				return $this->getTokenInfo ( $item [2], $result );
			}
		}
		return false;
	}

	/**
	 * 在获取最后一个token的时候，检测{和}个数是否相等
	 * @see Fl_Token::getLastToken()
	 */
	public function getLastToken() {
		if ($this->validate && $this->bracesNum [0] != $this->bracesNum [1]) {
			$this->throwException ( '`{` & `}` count not equal. please check.', false );
		}
		return parent::getLastToken ();
	}
}