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
		@license GNU AGPL v3
	*/
	// *************************************************
	class CDocumentation
	{
		private $data;
		private $current_position_in_file;
		private $comment_start = '/*';
		private $comment_end = '*/';
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
			$comment_start = $this->comment_start;
			$comment_end = $this->comment_end;
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
					else
						$i = 0;

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
				$next_non_comment_line = $this->getNextNonCommentLine();
				$ret = $this->parseNonCommentLine( $next_non_comment_line );

				if( $ret['name'] == '' )
					continue;

				$arr[$i] = $ret;

				$parsed_comment_block = $this->parseCommentBlock( 
					$comments );
				$cleaned_comment_block = $this->cleanMethodCommentBlock( 
					$parsed_comment_block );

				$arr[$i] = array_merge( $arr[$i], $cleaned_comment_block );
				$i++;
			}

			return $arr;
		}

		// *************************************************
		//	cleanMethodCommentBlock
		/*!
			@brief Read from $comments array 'comments' key
			  and all its values and store them to own keys.
			  For example $comments['@brief'][0] will be 
			  in key 'brief' and so on.
			@param $comments Array of comments where we haven't
			  yet exploded tags and values.
		*/
		// *************************************************
		private function cleanMethodCommentBlock( $comments )
		{
			$return_array = array();

			foreach( $this->tags as $tag_name )
			{
				// We can have more than one params so skip it at this time
				if( $tag_name == '@param' )
					continue;

				if( isset( $comments[$tag_name][0] ) )
				{
					$comment = $this->textToSafeHTML( 
						$comments[$tag_name][0] );

					$tag_without_at_char = substr( $tag_name, 1 );
					$return_array[$tag_without_at_char] = $comment;
				}
			}

			if( isset( $comments['@param'] ) )
			{
				for( $i=0; $i < count( $comments['@param'] ); $i++ )
				{
					$line = $comments['@param'][$i];
					$tmp = explode( ' ', $line );
					$param_name = $tmp[0];
					$param_value = substr( $line, 
						strlen( $param_name ) + 1 );

					$return_array['param'][$param_name] = $param_value;
				}
			}

			return $return_array;
		}

		// *************************************************
		//	textToSafeHTML
		/*!
			@brief Make string to HTML safe, so this will replace
			  < and > chars and make them to &lt; and &gt;
			@param $data Data to make safe
			@return String
		*/
		// *************************************************
		private function textToSafeHTML( $data )
		{
			$data = str_replace( '<', '&lt;', $data );
			$data = str_replace( '>', '&gt;', $data );

			return $data;
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

		// ************************************************** 
		//  makeOneLineCommentsToMultiLineComments
		/*!
			@brief Change one liner comments to multiline comments
			  so the parser won't fail.
		*/
		// ************************************************** 
		private function makeOneLineCommentsToMultiLineComments()
		{
			$comment_start = $this->comment_start;
			$comment_end = $this->comment_end;
			$new_data = array();

			foreach( $this->data as $current_line )
			{
				$first_chars = substr( $current_line, 0, 2 );
				$last_chars = substr( $current_line, 
					strlen( $current_line ) - strlen( $comment_end ),
					strlen( $comment_end ) );

				if( $first_chars == $comment_start 
					&& $last_chars == $comment_end )
				{
					$actual_comment = substr( $current_line,
						strlen( $comment_start ),
						strlen( $current_line ) 
							- strlen( $comment_start ) 
							- strlen( $comment_end ) );

					$new_data[] = $comment_start;
					$new_data[] = trim( $actual_comment );
					$new_data[] = $comment_end;
				}
				else
				{
					$new_data[] = $current_line;
				}
			}

			$this->data = $new_data;
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
			$this->makeOneLineCommentsToMultiLineComments();
		}
	}

?>
