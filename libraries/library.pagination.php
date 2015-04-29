<?php

namespace ionmvc\packages\pagination\libraries;

use ionmvc\classes\asset;
use ionmvc\classes\config;
use ionmvc\classes\library;
use ionmvc\classes\uri;
use ionmvc\exceptions\app as app_exception;

class pagination {

	private static $config = [];
	private static $count  = 0;

	private $built   = false;
	private $query   = null;
	private $results = null;
	private $data    = [];
	private $uri_config = [
		'all' => true
	];

	public function __construct( $config=array() ) {
		if ( self::$count === 0 ) {
			self::$config = config::get('pagination');
		}
		self::$count++;
		if ( !isset( $config['profile'] ) ) {
			$config['profile'] = self::$config['default_profile'];
		}
		if ( !isset( self::$config['profiles'][$config['profile']] ) ) {
			throw new app_exception( 'Unable to find the profile \'%s\'',$profile );
		}
		$this->data = array_merge( self::$config['profiles'][$config['profile']],$config );
		$required = ['page_uri_id','limit_uri_id','limit','allow_limit_change','adjacent'];
		if ( count( ( $keys = array_diff( $required,array_keys( $this->data ) ) ) ) > 0 ) {
			throw new app_exception( 'Missing config items: %s',implode( ', ',$keys ) );
		}
		if ( $this->data['allow_limit_change'] === true && !isset( $this->data['allowed_limits'] ) ) {
			throw new app_exception('Allowed limits config item is required when allowing limit changes');
		}
		if ( isset( $this->data['allowed_limits'] ) && !is_array( $this->data['allowed_limits'] ) ) {
			$this->data['allowed_limits'] = explode( ',',$this->data['allowed_limits'] );
		}
		$this->data['page_uri_id'] = $this->data['page_uri_id'] . ( self::$count == 1 ? '' : self::$count );
		$this->data['limit_uri_id'] = $this->data['limit_uri_id'] . ( self::$count == 1 ? '' : self::$count );
		$this->page( uri::segment( $this->data['page_uri_id'],1 ) );
		if ( ( $limit = uri::segment( $this->data['limit_uri_id'] ) ) !== false && $this->data['allow_limit_change'] === true && ( strtolower( $limit ) == 'all' || in_array( $limit,$this->data['allowed_limits'] ) ) ) {
			$this->data['limit'] = ( strtolower( $limit ) == 'all' ? false : (int) $limit );
		}
		if ( uri::segment('csm') !== false ) {
			$this->uri_config['csm'] = true;
		}
	}

	public function __get( $key ) {
		if ( isset( $this->data[$key] ) ) {
			return $this->data[$key];
		}
		switch( $key ) {
			case 'total_pages':
			case 'prev_page':
			case 'next_page':
			case 'last_page':
			case 'total_pages':
			case 'result_start':
			case 'result_end':
			case 'is_last_page':
				if ( $this->built === false ) {
					throw new app_exception( 'Data not available for \'%s\'. Must run build() first.',$key );
				}
			break;
		}
		return false;
	}

	public function page( $page ) {
		$this->data['page'] = (int) $page;
		return $this;
	}

	public function total_results( $total ) {
		$this->data['total_results'] = (int) $total;
		return $this;
	}

	public function limit( $limit ) {
		$this->data['limit'] = (int) $limit;
		return $this;
	}

	public function adjacent( $adj ) {
		$this->data['adjacent'] = (int) $adj;
		return $this;
	}

	public function results( $array ) {
		if ( !is_null( $this->query ) ) {
			throw new app_exception('Query already defined');
		}
		$this->results = $array;
		return $this;
	}

	public function query( $query ) {
		if ( !is_null( $this->results ) ) {
			throw new app_exception('Result array already defined');
		}
		$this->query = $query;
		return $this;
	}

	public function build() {
		if ( !isset( $this->data['total_results'] ) ) {
			if ( is_null( $this->results ) ) {
				$query = clone $this->query;
				$query = $query->clear_fields(false)->count('*','count')->use_clauses('join','where','group_by','having')->execute();
				$this->data['total_results'] = 0;
				if ( $query->num_rows() === 1 ) {
					$this->data['total_results'] = $query->result()->count;
				}
			}
			else {
				$this->data['total_results'] = count( $this->results );
			}
		}
		$this->data['total_pages'] = $this->data['last_page'] = ( $this->data['limit'] === false ? 1 : ceil( $this->data['total_results'] / $this->data['limit'] ) );
		if ( $this->data['page'] > $this->data['total_pages'] ) {
			$this->data['page'] = $this->data['last_page'];
		}
		if ( $this->data['page'] < 1 ) {
			$this->data['page'] = 1;
		}
		$this->data['prev_page'] = ( $this->data['page'] - 1 );
		if ( $this->data['prev_page'] < 1 ) {
			$this->data['prev_page'] = 1;
		}
		$this->data['next_page'] = ( $this->data['page'] + 1 );
		if ( $this->data['next_page'] > $this->data['last_page'] ) {
			$this->data['next_page'] = $this->data['last_page'];
		}
		if ( is_null( $this->results ) && $this->data['limit'] !== false ) {
			$this->query->limit( ( ( $this->data['limit'] * $this->data['page'] ) - $this->data['limit'] ),$this->data['limit'] );
		}
		elseif ( !is_null( $this->results ) && $this->data['limit'] !== false ) {
			$this->results = array_slice( $this->results,( ( $this->data['limit'] * $this->data['page'] ) - $this->data['limit'] ),$this->data['limit'] );
		}
		$this->data['result_start'] = ( $this->data['limit'] === false ? 1 : ( ( $this->data['limit'] * $this->data['page'] ) - $this->data['limit'] ) );
		$this->data['result_end'] = ( $this->data['limit'] === false ? $this->data['total_results'] : ( $this->data['result_start'] + $this->data['limit'] ) );
		if ( $this->data['result_end'] > $this->data['total_results'] ) {
			$this->data['result_end'] = $this->data['total_results'];
		}
		if ( $this->data['result_start'] !== 1 ) {
			$this->data['result_start'] += 1;
		}
		if ( $this->data['result_end'] == 0 ) {
			$this->data['result_start'] = 0;
		}
		$this->data['is_last_page'] = ( $this->data['last_page'] == $this->data['page'] ? true : false );
		$this->built = true;
	}

	protected function build_limit_uri( $limit ) {
		return uri::create( [
			$this->data['limit_uri_id'] => $limit
		],$this->uri_config );
	}

	protected function build_page_uri( $page ) {
		return uri::create( [
			$this->data['page_uri_id'] => $page
		],$this->uri_config );
	}

	public function output() {
		if ( $this->built === false ) {
			$this->build();
		}
		if ( isset( $this->data['css'] ) && $this->data['css'] !== false ) {
			asset::add( $this->data['css'] );
		}
		$lpm1 = ( $this->data['last_page'] - 1 );
		$html = '<div class="m-pagination">';
		if ( $this->data['allow_limit_change'] === true ) {
			$html .= '<div class="c-p-limit"><span class="c-pl-label">Display: </span><select class="c-pl-select" onchange="window.location=this.options[this.selectedIndex].value">';
			foreach( $this->data['allowed_limits'] as $limit ) {
				$html .= '<option ' . ( $limit == $this->data['limit'] ? 'selected="selected" ' : '' ) . 'value="' . $this->build_limit_uri( $limit ) . "\">{$limit}</option>";
			}
			$html .= '<option ' . ( $this->data['limit'] === false ? 'selected="selected" ' : '' ) . 'value="' . $this->build_limit_uri('all') . '">All</option></select></div>';
		}
		if ( $this->data['last_page'] > 1 ) {
			if ( $this->data['page'] > 1 ) {
				$html .= '<a class="c-p-control" href="' . $this->build_page_uri( $this->data['prev_page'] ) . '">&laquo;&nbsp;Prev</a>';
			}
			else {
				$html .= '<span class="c-p-control t-disabled">&laquo;&nbsp;Prev</span>';
			}
			if ( $this->data['last_page'] < ( 7 + ( $this->data['adjacent'] * 2 ) ) ) {
				for ( $i = 1; $i <= $this->data['last_page']; $i++ ) {
					if ( $i == $this->data['page'] ) {
						$html .= "<span class=\"c-p-link t-current\">{$i}</span>";
					}
					else {
						$html .= '<a class="c-p-link" href="' . $this->build_page_uri( $i ) . "\">{$i}</a>";
					}
				}
			}
			elseif ( $this->data['last_page'] >= ( 7 + ( $this->data['adjacent'] * 2 ) ) ) {
				if ( $this->data['page'] < ( 1 + ( $this->data['adjacent'] * 3 ) ) ) {
					for ( $i = 1; $i < ( 4 + ( $this->data['adjacent'] * 2 ) ); $i++ ) {
						if ( $i == $this->data['page'] ) {
							$html .= "<span class=\"c-p-link t-current\">{$i}</span>";
						}
						else {
							$html .= '<a class="c-p-link" href="' . $this->build_page_uri( $i ) . "\">{$i}</a>";
						}
					}
					$html .= '<span class="class="c-p-ellipses" ">…</span>';
					$html .= '<a class="c-p-link" href="' . $this->build_page_uri( $lpm1 ) . "\">{$lpm1}</a>";
					$html .= '<a class="c-p-link" href="' . $this->build_page_uri( $this->data['last_page'] ) . "\">{$this->data['last_page']}</a>";
				}
				elseif ( ( $this->data['last_page'] - ( $this->data['adjacent'] * 2 ) ) > $this->data['page'] && $this->data['page'] > ( $this->data['adjacent'] * 2 ) ) {
					$html .= '<a class="c-p-link" href="' . $this->build_page_uri(1) . '">1</a>';
					$html .= '<a class="c-p-link" href="' . $this->build_page_uri(2) . '">2</a>';
					$html .= '<span class="c-p-ellipses">…</span>';
					for ( $i = ( $this->data['page'] - $this->data['adjacent'] ); $i <= ( $this->data['page'] + $this->data['adjacent'] ); $i++ ) {
						if ( $i == $this->data['page'] ) {
							$html .= "<span class=\"c-p-link t-current\">{$i}</span>";
						}
						else {
							$html .= '<a class="c-p-link" href="' . $this->build_page_uri( $i ) . "\">{$i}</a>";
						}
					}
					$html .= '<span class="c-p-ellipses">…</span>';
					$html .= '<a class="c-p-link" href="' . $this->build_page_uri( $lpm1 ) . "\">{$lpm1}</a>";
					$html .= '<a class="c-p-link" href="' . $this->build_page_uri( $this->data['last_page'] ) . "\">{$this->data['last_page']}</a>";
				}
				else {
					$html .= '<a class="c-p-link" href="' . $this->build_page_uri(1) . '">1</a>';
					$html .= '<a class="c-p-link" href="' . $this->build_page_uri(2) . '">2</a>';
					$html .= '<span class="c-p-ellipses">…</span>';
					for ( $i = ( $this->data['last_page'] - ( 1 + ( $this->data['adjacent'] * 3 ) ) ); $i <= $this->data['last_page']; $i++ ) {
						if ( $i == $this->data['page'] ) {
							$html .= "<span class=\"c-p-link t-current\">{$i}</span>";
						}
						else {
							$html .= '<a class="c-p-link" href="' . $this->build_page_uri( $i ) . "\">{$i}</a>";
						}
					}
				}
			}
			if ( $this->data['page'] < ( $i - 1 ) ) {
				$html .= '<a class="c-p-control" href="' . $this->build_page_uri( $this->data['next_page'] ) . '">Next&nbsp;&raquo;</a>';
			}
			else {
				$html .= '<span class="c-p-control t-disabled">Next&nbsp;&raquo;</span>';
			}
		}
		$html .= '</div>';
		return $html;
	}

	public function get_results() {
		if ( $this->built === false ) {
			$this->build();
		}
		return $this->results;
	}

}

?>