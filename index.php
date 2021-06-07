<?php
/***************

Add you Airtable API key and base below.
Also, update the path to Parsedown.php

***************/

$api = '';
$base = '';

// If there are more than 100 entries in your Airtable, this will get them for you //
function offset($query, $offset, $more){
        
    $new_query = $query . '&offset=' . $offset;
    $new_data = file_get_contents($new_query);
    $new_data_parsed = json_decode($new_data);
    
    if(isset($new_data_parsed->{'offset'})){
        $new_offset = $new_data_parsed->{'offset'};
        $more = array_merge($more, $new_data_parsed->{'records'});
        $more = offset($query, $new_offset, $more);
    } else {
        $more = array_merge($more, $new_data_parsed->{'records'});
    }
    return $more;
}

$today = date('Y-m-d');
$today_compare = date('Y-m-d', strtotime($today));

include_once('[UPDATE THE PATH HERE]/Parsedown.php');
?>

<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>

	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<meta content="width = device-width, initial-scale = 1, maximum-scale = 1, user-scalable = no" name="viewport">
	<title>I Am Training for the 2021 New York City Marathon</title>
	<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="./css/style.css" media="screen">
</head>

<body>

	<?php
	$tp_query = 'https://api.airtable.com/v0/' . $base . '/Training%20Plan?api_key=' . $api . '&view=Grid%20view';
	$training_plan = file_get_contents($tp_query);
	$tp_parsed = json_decode($training_plan);

	// Check if there are more records that need to be queried and go get them. //
    if(isset($tp_parsed->{'offset'})){
        $offset = $tp_parsed->{'offset'};
        $more = [];
        $more = offset($tp_query, $offset, $more);
        $tp_parsed->{'records'} = array_merge($tp_parsed->{'records'}, $more);
    }

    $atr_query = 'https://api.airtable.com/v0/' . $base . '/Runs?api_key=' . $api . '&filterByFormula={Training%20Plan}!=""';
    $all_the_runs = file_get_contents($atr_query);
	$atr_parsed = json_decode($all_the_runs);

	// Check if there are more records that need to be queried and go get them. //
    if(isset($atr_parsed->{'offset'})){
        $offset = $atr_parsed->{'offset'};
        $more = [];
        $more = offset($atr_query, $offset, $more);
        $atr_parsed->{'records'} = array_merge($atr_parsed->{'records'}, $more);
    }

	$atr_records = $atr_parsed->{'records'};

	$byWeek = array(); 
	foreach ($tp_parsed->{'records'} as $item) {
	    $date = DateTime::createFromFormat('Y-m-d', $item->{'fields'}->{'Date'});

	    $firstDayOfWeek = 1; // Monday

	    $difference = ($firstDayOfWeek - $date->format('N'));
	    if ($difference > 0) { $difference -= 7; }
	    $date->modify("$difference days");
	    $monday = $date->format('Y-m-d');
	    $nextSunday = date('Y-m-d', strtotime('next sunday', strtotime($monday)));
	    $week = $date->format('W');

	    if(!isset($byWeek[$week])){
	        $byWeek[$week] = [];
	        $byWeek[$week]['tpEntries'] = [];
	        $byWeek[$week]['startenddates']['weekstart'] = $monday;
	        $byWeek[$week]['startenddates']['weekend'] = $nextSunday;
	    }
	    array_push($byWeek[$week]['tpEntries'], $item);
	}

	?>

	<header>
		<h1>I am training for the 2021 New York City Marathon.</h1>
		<div class="intro_text">
			<p>It&rsquo;s on.</p>
		</div>
		<div class="contact">
			Contact me at<br>
			<a href="mailto:johndoe@gmail.com">johndoe@gmail.com</a><br><br>

			Follow me on<br>
			<a href="#" target="_blank">Strava</a><br>
			<a href="#" target="_blank">Twitter</a><br>
			<a href="#" target="_blank">Instagram</a>
		</div>
	</header>
	<div class="wrapper">

		<?php
		foreach ($byWeek as $bw) {
			$start = $bw['startenddates']['weekstart'];
			$end = $bw['startenddates']['weekend'];

			if (($today_compare >= $start) && ($today_compare <= $end)){
			    $current = ' current';
			} else {
			    $current = '';
			}
			$bc_count = 'biweek_';

			$biweekly_milage = 0;
			$biweekly_estimate = 0;

			?>
			<div class="biweek_wrapper<?= $current ?>">
				<div class="biweek">
					<?php foreach ($bw['tpEntries'] as $day) {
						$estimated_milage = round($day->{'fields'}->{'Estimated Milage'}, 2);
						$biweekly_estimate += $estimated_milage;
						if(isset($day->{'fields'}->{'Linked Strava Activities'})){
							$done = ' done';
							$activity_ids = $day->{'fields'}->{'Linked Strava Activities'};

							$rds = [];
							foreach ($activity_ids as $activity_id) {
								foreach ($atr_records as $atr_record) {
									$training_plan = $atr_record->{'id'};
									if($training_plan === $activity_id){
										array_push($rds, $atr_record);
									}
								}
							}
							
							$day_milage = 0;
							$related_runs = '<div class="related_runs">';
							foreach ($rds as $run) {
								$notes = '';
								$day_milage += $run->{'fields'}->{'Distance Miles'};

								$related_runs .= '<div class="related_run">';
									$related_runs .= '<div class="time_of_day">';
										$related_runs .= date('g:ia', strtotime($run->{'fields'}->{'Created At'}));
									$related_runs .= '</div>';
									$related_runs .= '<div class="run_title">';
										$related_runs .= '<a href="' . $run->{'fields'}->{'Link to Activity'} . '" target="_blank">' . $run->{'fields'}->{'Name'} . '</a>';
									$related_runs .= '</div>';
									$related_runs .= '<div class="run_data">';
										$related_runs .= '<div class="run_milage">';
											$related_runs .= round($run->{'fields'}->{'Distance Miles'}, 2) . ' mi';
										$related_runs .= '</div>';
										$related_runs .= '<div class="run_pace">';
											$minutesWithoutZero = 1 * gmdate( 'i', $run->{'fields'}->{'Pace Per Mile'});
											$seconds = gmdate( 's', $run->{'fields'}->{'Pace Per Mile'});
											$related_runs .=  $minutesWithoutZero . ':' . $seconds . ' /mi';
										$related_runs .= '</div>';
										$related_runs .= '<div class="run_elapsed">';
											
											$elapsed_hr = 1 * gmdate( 'H', $run->{'fields'}->{'Elapsed Time in Seconds'});
											$elapsed_min = 1 * gmdate( 'i', $run->{'fields'}->{'Elapsed Time in Seconds'});
											$elapsed_sec = gmdate( 's', $run->{'fields'}->{'Elapsed Time in Seconds'});

											if($elapsed_hr > 0){
												$related_runs .= $elapsed_hr . 'h ';
											}
											
											$related_runs .= $elapsed_min . 'm ';
											$related_runs .= $elapsed_sec . 's';
											
										$related_runs .= '</div>';
									$related_runs .= '</div>';
									if(isset($run->{'fields'}->{'Notes'})){
										$notes = $run->{'fields'}->{'Notes'};
										$related_runs .= '<div class="run_notes">';
											$related_runs .= Parsedown::instance()
												->setBreaksEnabled(true) # enables automatic line breaks
												->text($notes);
											//$related_runs .= $notes;
										$related_runs .= '</div>';
									}
									
								$related_runs .= '</div>';
							}
							$day_milage = round($day_milage, 2);
							$biweekly_milage += $day_milage;
							$milage_text = 'Milage completed: ' . $day_milage;

							$related_runs .= '</div>';

						} else {
							$done = '';
							$related_runs = '';
							$milage_text = 'Estimated milage: ' . $estimated_milage;
						} ?>
						<?php if($day->{'fields'}->{'Run Type'} === 'Crosstrain'){
							$extraClass = ' crosstrain';
						} else {
							$extraClass = '';
						} ?>
						<item class="day<?= $done ?><?= $extraClass ?>">
							<div class="date">
								<?= date('D, M j', strtotime($day->{'fields'}->{'Date'})) ?>
							</div>
							<div class="run_type">
								<?= $day->{'fields'}->{'Run Type'} ?>
							</div>
							<div class="milage">
								<?= $milage_text ?>
							</div>
							<div class="run_description">
								Training plan: <?php echo Parsedown::instance()->setBreaksEnabled(true)->text($day->{'fields'}->{'Run Description'}) . '<br>'; ?>
							</div>
							<?= $related_runs ?>
						</item>
					<?php } ?>
				</div>
				<div class="bw_totals">
					<div class="milage">
						<strong>Estimated miles in cycle:</strong> <?= $biweekly_estimate ?><br>
						<strong>Total miles run in cycle:</strong> <?= $biweekly_milage ?>
					</div>
				</div>
			</div>

		<?php }
		?>

	</div><!-- /div.wrapper -->
</body>
</html>