<?php

/// Pagination class by Ronsse Maxim.
/// Pass the pagination arguments to the constructor. Then call getPages which returns an associative array containing the pages. Array example:
/// array(5) {
///		1 => '1',
///		2 => '2',
///		3 => '...',
///		4 => '3',
///		5 => '5'
/// }
/// Note: all page numbers start at 1

class Pagination {
	private $currentPage; // current page the user is on
	private $numPages; // total amount of pages
	private $limit; // max pages before the separator is added
	private $breakpoint; // position where the separator is added (<= $limit)
	private $separator; // separator string
	private $logicNumbers = false; // whether or not the numbers start at 0. (false = no, true = yes)


	// constructor
	public function __construct($currentPage = 1, $numPages = 1, $limit = 7, $separator = '...', $breakpoint = 4) {
		$this->currentPage = $currentPage;
		$this->numPages = $numPages;
		$this->limit = $limit;
		$this->separator = $separator;
		$this->breakpoint = $breakpoint;
	}

	// get the pages, using the variables passed to the constructor
	public function getPages() {
		$items = array();

		/**
		 * Less than or 7 pages. We know all the keys, and we put them in the array
		 * that we will use to generate the actual pagination.
		 */
		if($this->numPages <= $this->limit) {
			for($i = 1-(bool)$this->logicNumbers; $i <= $this->numPages-(bool)$this->logicNumbers; $i++) $items[$i] = $i+(bool)$this->logicNumbers;
		}

		// more than 7 pages
		else {
			// first page
			if($this->currentPage == 1-(bool)$this->logicNumbers) {
				// [1] 2 3 4 5 6 7 8 9 10 11 12 13
				for($i = 1; $i <= $this->limit; $i++) $items[$i] = $i+(bool)$this->logicNumbers;
				$items[$this->limit + 1] = $this->separator;
			}


			// last page
			else
			if($this->currentPage == $this->numPages) {
				// 1 2 3 4 5 6 7 8 9 10 11 12 [13]
				$items[$this->numPages - $this->limit - 1] = $this->separator;
				for($i = ($this->numPages - $this->limit); $i <= $this->numPages; $i++) $items[$i] = $i+(bool)$this->logicNumbers;
			}

			// other page
			else
			{
				// 1 2 3 [4] 5 6 7 8 9 10 11 12 13

				// define min & max
				$min = $this->currentPage - $this->breakpoint + 1;
				$max = $this->currentPage + $this->breakpoint - 1;

				// minimum doesnt exist
				while($min <= 0)
				{
					$min++;
					$max++;
				}

				// maximum doesnt exist
				while($max > $this->numPages)
				{
					$min--;
					$max--;
				}

				// create the list
				if($min != 1) $items[$min - 1] = $this->separator;
				for($i = $min; $i <= $max; $i++) $items[$i] = $i+(bool)$this->logicNumbers;
				if($max != $numPages) $items[$max + 1] = $this->separator;
			}
		}

		return $items;
	}
}