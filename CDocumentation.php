<?php

/* 
CDocumentation - Class for comments parsing from PHP files.
Copyright (C) 2011 Aleksi Räsänen <aleksi.rasanen@runosydan.net>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as
published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Affero General Public License for more details.

You should have received a copy of the GNU Affero General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

	// *************************************************
	//	CDocumentation
	/*!
		@brief Very simple document generator class

		@copyright Aleksi Räsänen 2011

		@author Aleksi Räsänen

		@email aleksi.rasanen@runosydan.net

		@license GNU AGPL
	*/
	// *************************************************
	class CDocumentation
	{
		private $data;
		private $current_position_in_file;
		private $tags = array( 
			'@brief', '@param', '@return', '@author',
			'@copyright', '@email', '@license' );

		// *************************************************
		//	getNextCommentBlock
		/*!
			@brief Get next comment block from file.
			  We know current position from class variable
			  $current_position_in_file.

			@return Array
		*/
		// *************************************************
		private function getNextCommentBlock()
		{
			$comment_start = '/*';
			$comment_end = '*/';
			$block_started = false;
			$comments = array();
			$first_line = $this->current_position_in_file;

			for( $i=$first_line; $i < count( $this->data ); $i++ )
			{
				$current_line = $this->data[$i];
				$first_chars = substr( $current_line, 0, 2 );

				if( $first_chars == $comment_end && $block_started )
				{
					$this->current_position_in_file = $i;
					break;
				}

				if( $block_started && $current_line != '' )
					$comments[] = $current_line;

				if( $first_chars == $comment_start )
					$block_started = true;
			}

			return $comments;
		}

		// *************************************************
		//	getNextNonCommentLine
		/*!
			@brief Get next line what is not a comment line.
			  This is used when we want to get function, method,
			  class name or whatever where last grabbed 
			  comment belongs.
			
			@return String
		*/
		// *************************************************
		private function getNextNonCommentLine()
		{
			$first_line = $this->current_position_in_file;
			$non_valids = array( '//', '/*', '*/' );

			for( $i=$first_line; $i < count( $this->data ); $i++ )
			{
				$current_line = $this->data[$i];
				$first_chars = substr( $current_line, 0, 2 );

				if( in_array( $first_chars, $non_valids ) )
					continue;

				return $this->data[$i];
			}
		}

		// *************************************************
		//	parseNonCommentLine
		/*!
			@brief Parse a line what is not a comment line.
			  This will read a line where is defined if
			  this is a function, private/public method,
			  class and so on.

			@return Array
		*/
		// *************************************************
		private function parseNonCommentLine( $line )
		{
			$tmp = explode( ' ', $line );
			$type = $tmp[0];
			$name = '';

			if( $type == 'public' || $type == 'private' )
				$name = $tmp[2];
			else
			{
				if( isset( $tmp[1] ) )
					$name = $tmp[1];
			}

			$chars_to_replace = array( '(', ')' );
			$name = str_replace( $chars_to_replace, '', $name );

			return array( 'name' => $name, 'type' => $type );
		}

		// *************************************************
		//	parseCommentBlock
		/*!
			@brief Parse comment block and pick variables
			  and set values to them

			@param $comments Comments array
			
			@return Array
		*/
		// *************************************************
		private function parseCommentBlock( $comments )
		{
			$current_tag = '';
			$ret = array();
			$i = 0;

			foreach( $comments as $line )
			{
				$tmp = explode( ' ', $line );
				$line_to_add = $line . ' ';

				if( in_array( $tmp[0], $this->tags ) )
				{
					$current_tag = $tmp[0];

					if( isset( $ret[$current_tag][$i] ) )
						$i++;

					$line_to_add = substr( $line, 
						strlen( $tmp[0] ) + 1 ) . ' ';
				}

				if( isset( $ret[$current_tag][$i] ) )
					$ret[$current_tag][$i] .= $line_to_add;
				else
					$ret[$current_tag][$i] = $line_to_add;
			}

			return $ret;
		}

		// *************************************************
		//	parseFile
		/*!
			@brief Parse PHP file and search for comments
		*/
		// *************************************************
		public function parseFile()
		{
			$arr = array();
			$i=0;

			while( count( $comments = $this->getNextCommentBlock() ) != 0 )
			{
				$ret = $this->getNextNonCommentLine();
				$ret = $this->parseNonCommentLine( $ret);

				if( $ret['name'] == '' )
					continue;

				$arr[$i] = $ret;

				$ret = $this->parseCommentBlock( $comments );
				$arr[$i]['comments'] = $ret;

				$i++;
			}

			return $arr;
		}

		// *************************************************
		//	readFile
		/*!
			@brief Read file and store it content to
			  class variable $data

			@param $filename File to read
		*/
		// *************************************************
		private function readFile( $filename )
		{
			$tmp = explode( "\n", file_get_contents( $filename ) );
			
			foreach( $tmp as $line )
				$this->data[] = trim( $line );
		}

		// *************************************************
		//	getParsedData
		/*!
			@brief Returns parsed data from file

			@return Array.
		*/
		// *************************************************
		public function getParsedData()
		{
			return $this->parseFile();
		}

		// *************************************************
		//	__construct
		/*!
			@brief Get comments for class methods

			@param $filename File to parse
		*/
		// *************************************************
		public function __construct( $filename )
		{
			if(! file_exists( $filename ) )
				return;

			$this->current_position_in_file = 0;
			$this->readFile( $filename );
		}
	}

?>
