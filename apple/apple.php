<?php
	$apple_ages = [
		'18-24' => [],
		'25-34' => [],
		'35-44' => [],
		'45-54' => [],
		'55-64' => [],
		'65+' => [],
	];

	$sheet = 'Apple News';
	$row = 0;
	$ao = 0;
	/**
	 * Open the CSV report downloaded from Apple News. They have a way to set up weekly reports that they email you
	 * 		which makes downloading the report a lot easier. The report referenced below is the "Channel Summary" report.
	 * 		I then save it in the 'apple' folder with a filename 'channel-YYYY-MM-DD.csv' (same as the report end date)
	 *
	 */
	if ( ( $handle = fopen( BASE . DS . "apple" . DS . "channel-" . $end . ".csv", "r" ) ) !== FALSE ) :
		while ( ( $data = fgetcsv( $handle, 1000, "," ) ) !== FALSE ) :
			if ( $row === 0 ) :
				$sheets[$sheet][] = [
					// Header row for the spreadsheet
					'Date','Total Views','Unique Views','Reach','Shares','Likes','Favorites','Saved Articles','Male Users','Female Users','Users 18-24','Users 25-34','Users 35-44','Users 45-54','Users 55-64','Users 65+'
				];
				if ( $data[0] == 'Channel' ) :
					$ao = 1;
				endif;
			else :

				/**
				 * Mapping all of the CSV fields into the graphing data structure
				 * Demographics by gender
				 */
				$graphs['apple-demo']['labels'][] = $data[0+$ao];
				$graphs['apple-demo']['datasets'][0]['data'][] = $data[19+$ao] * 100;
				$graphs['apple-demo']['datasets'][1]['data'][] = $data[20+$ao] * 100;

				/**
				 * Total views, unique views and overall reach
				 */
				$graphs['apple-reach']['labels'][] = $data[0+$ao];
				$graphs['apple-reach']['datasets'][0]['data'][] = ( empty( intval( $data[1+$ao] ) ) ? 0 : intval( $data[1+$ao] ) );
				$graphs['apple-reach']['datasets'][1]['data'][] = ( empty( intval( $data[2+$ao] ) ) ? 0 : intval( $data[2+$ao] ) );
				$graphs['apple-reach']['datasets'][2]['data'][] = ( empty( intval( $data[18+$ao] ) ) ? 0 : intval( $data[18+$ao] ) );

				/**
				 * Shares, likes, favorites, saved articles
				 */
				$graphs['apple-engage']['labels'][] = $data[0+$ao];
				$graphs['apple-engage']['datasets'][0]['data'][] = ( empty( intval( $data[3+$ao] ) ) ? 0 : intval( $data[3+$ao] ) );
				$graphs['apple-engage']['datasets'][1]['data'][] = ( empty( intval( $data[9+$ao] ) ) ? 0 : intval( $data[9+$ao] ) );
				$graphs['apple-engage']['datasets'][2]['data'][] = ( empty( intval( $data[10+$ao] ) ) ? 0 : intval( $data[10+$ao] ) );
				$graphs['apple-engage']['datasets'][3]['data'][] = ( empty( intval( $data[13+$ao] ) ) ? 0 : intval( $data[13+$ao] ) );

				/**
				 * User breakdown by age range. Currently trending majority 50+
				 * This breakdown is presented by day, so I'm saving it to the side for averaging
				 */
				$apple_ages['18-24'][] = ( empty( $data[21+$ao] ) ? '0%' : ($data[21+$ao] * 100).'%' );
				$apple_ages['25-34'][] = ( empty( $data[22+$ao] ) ? '0%' : ($data[22+$ao] * 100).'%' );
				$apple_ages['35-44'][] = ( empty( $data[23+$ao] ) ? '0%' : ($data[23+$ao] * 100).'%' );
				$apple_ages['45-54'][] = ( empty( $data[24+$ao] ) ? '0%' : ($data[24+$ao] * 100).'%' );
				$apple_ages['55-64'][] = ( empty( $data[25+$ao] ) ? '0%' : ($data[25+$ao] * 100).'%' );
				$apple_ages['65+'][] = ( empty( $data[26+$ao] ) ? '0%' : ($data[26+$ao] * 100).'%' );

				// Save all of that info into a row in the spreadsheet
				$sheets[$sheet][] = [
					$data[0+$ao],
					( empty( intval( $data[1+$ao] ) ) ? 0 : intval( $data[1+$ao] ) ),
					( empty( intval( $data[2+$ao] ) ) ? 0 : intval( $data[2+$ao] ) ),
					( empty( intval( $data[18+$ao] ) ) ? 0 : intval( $data[18+$ao] ) ),
					( empty( intval( $data[3+$ao] ) ) ? 0 : intval( $data[3+$ao] ) ),
					( empty( intval( $data[9+$ao] ) ) ? 0 : intval( $data[9+$ao] ) ),
					( empty( intval( $data[10+$ao] ) ) ? 0 : intval( $data[10+$ao] ) ),
					( empty( intval( $data[13+$ao] ) ) ? 0 : intval( $data[13+$ao] ) ),
					( empty( $data[19+$ao] ) ? '0%' : ($data[19+$ao] * 100).'%' ),
					( empty( $data[20+$ao] ) ? '0%' : ($data[20+$ao] * 100).'%' ),
					( empty( $data[21+$ao] ) ? '0%' : ($data[21+$ao] * 100).'%' ),
					( empty( $data[22+$ao] ) ? '0%' : ($data[22+$ao] * 100).'%' ),
					( empty( $data[23+$ao] ) ? '0%' : ($data[23+$ao] * 100).'%' ),
					( empty( $data[24+$ao] ) ? '0%' : ($data[24+$ao] * 100).'%' ),
					( empty( $data[25+$ao] ) ? '0%' : ($data[25+$ao] * 100).'%' ),
					( empty( $data[26+$ao] ) ? '0%' : ($data[26+$ao] * 100).'%' )
				];
				$graphs['overall-totals']['apple-news']['data'] += ( empty( intval( $data[18+$ao] ) ) ? 0 : intval( $data[18+$ao] ) );
			endif;
			$row++;
		endwhile;
		fclose($handle);
	endif;

	/**
	 * Creating weekly averages for the different age ranges, for inclusion in the graphs
	 */
	foreach ( $apple_ages as $k => $v ) :
		$graphs['apple-age']['labels'][] = $k;
		$avg = round( array_sum( $v ) / count( $v ), 1 );
		$graphs['apple-age']['datasets'][0]['data'][] = $avg;
	endforeach;
?>