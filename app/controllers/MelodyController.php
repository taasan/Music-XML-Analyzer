<?php

class MelodyController {

	static $result;
	static $results;
	static $patternArray;
	static $xmlArray;
	static $xmlPositionArray;
	static $once;
	static $restDuration;
	static $noteCounter;

	function __construct() {
	

	}

	public function search($pattern) {
// dummy melody pattern:
// melody: [
// 	{
// 		type: "note",
//		pitch :
//			{
//				step: "C",
//				type: "half",
//				alter: 0,
//				octave: 3
//			}
// 	},
// 	{ 
// 		type: "rest",
// 		duration: "16th"
// 	}
// ]
	
		$p = $pattern[0]->notes;
		self::$patternArray = array();
		self::$results = array();

		//get note intervals of pattern
		foreach ($p as $note) {
			if($note->type == "note"){
				$interval = PatternController::getInterval($note);
				$obj = new stdClass();
				$obj->interval = $interval;
				$obj->type = $note->pitch->type;
				array_push(self::$patternArray, $obj);
			}else{
				array_push(self::$patternArray, $note->duration);
			}
		}

		//get user uploads & file_id's & file_url
		$user = User::find(Cookie::get('user_id'));
		$user->uploads->each(function($upload) {
			$xml = simplexml_load_file($upload->url);
			$file_id = $upload->id;
			$file_url = $upload->url;

			self::$once = true;
			self::$xmlArray = array(); 
			self::$xmlPositionArray = array();
			self::$result = new stdClass();
			self::$result->occurences = array();

			$parts = $xml->xpath("//part");


			foreach($parts as $part){
				self::$noteCounter = 0;
				for($i = 0; $i < count($part->measure); $i++){
					
					if($i == 0){
						//get division for calculation of rest duration once
						$partDivision = $part->measure[$i]->attributes->divisions;
						//get beat-type for calculation of rest duration once
						$partBeatType = $part->measure[$i]->attributes->time->{'beat-type'};
					}

					//get beat-type changes within measures
					if($i>0){ //no changes within first round
						// if changes occure
						if(isset($part->measure[$i]->attributes->time->{'beat-type'})){
							// get changes
							$partBeatType = $part->measure[$i]->attributes->time->{'beat-type'};
						}
					}
					for($j = 0; $j < count($part->measure[$i]->note); $j++){
						self::$noteCounter++;
						$n = $part->measure[$i]->note[$j];

						if(self::$once){
							self::$once = false;
							$lastVoice = $part->measure[$i]->note[$j]->voice;
						}

						if((int)$n->voice == (int)$lastVoice){
							$pitch = new stdClass();
							$pitch->step = $n->pitch->step;
							$pitch->alter = $n->pitch->alter;
							$pitch->octave = $n->pitch->octave;


							$note = new stdClass();
							$note->pitch = $pitch;
							$note->voice = $n->voice;
							// $note->type = $n->type;
							$note->position = self::$noteCounter;

							// if note
							if(!$n->rest){

								$obj = new stdClass();
								$obj->interval = PatternController::getInterval($note);
								$obj->type = $n->type;

								array_push(self::$xmlArray, $obj);
								array_push(self::$xmlPositionArray, $note->position);

							}
							// else if rest
							else{
								// calculate rest duration
								try{
									$restDurationFloat = (float)((int)$n->duration / (int)$partDivision / (int)$partBeatType);
								} catch (Exception $e) {
								    // Debugbar::info($n->duration);
								    // Debugbar::info($partDivision);
								    // Debugbar::info($partBeatType);
								    echo 'Exception abgefangen: ',  $e->getMessage(), "\n";
								}

	// rest durations: "whole" "half" "quarter" "eighth" "16th" "32nd" "64th"
								// determine 'type'
								if($restDurationFloat == 1){
									self::$restDuration = "whole";
								}elseif($restDurationFloat == 0.5){
									self::$restDuration = "half";
								}elseif($restDurationFloat == 0.25){
									self::$restDuration = "quarter";
								}elseif($restDurationFloat == 0.125){
									self::$restDuration = "eighth";
								}elseif($restDurationFloat == 0.0625){
									self::$restDuration = "16th";
								}elseif($restDurationFloat == 0.03125){
									self::$restDuration = "32nd";
								}elseif($restDurationFloat == 0.015625){
									self::$restDuration = "64th";
								}else{
									// 
									// ERROR mit "0,75" -> punktierte halbe?
									// 
									// Debugbar::info($restDurationFloat);
									// echo 'Rest duration unclear: ',  $restDurationFloat, "<br>";
									// echo $restDurationFloat, $n->duration, $partDivision, $partBeatType, "<br>";
								}
								array_push(self::$xmlArray, self::$restDuration);
								array_push(self::$xmlPositionArray, $note->position);

							}

							//check if Array-length equals Pattern-length already
							if(count(self::$xmlArray) == count(self::$patternArray)){
								
								// compare arrays
								if(array_values(self::$xmlArray) == array_values(self::$patternArray)){
									// create result
									self::$result->file_id = $file_id;
									self::$result->file_url = $file_url;

									//fill with occurences
									$occ = new stdClass();
									$occ->start = reset(self::$xmlPositionArray);
									$occ->end = end(self::$xmlPositionArray);
									$occ->voice = (int)$note->voice;
									$occ->part_id = (string)$part['id'];

									array_push(self::$result->occurences, $occ);

									//reset arrays
									self::$xmlArray = array();
									self::$xmlPositionArray = array();

								}else{

									self::$xmlArray = array_splice(self::$xmlArray, 1);

									self::$xmlPositionArray = array_splice(self::$xmlPositionArray, 1);

									self::$xmlArray = array_values(self::$xmlArray);

									self::$xmlPositionArray = array_values(self::$xmlPositionArray);

								}

								} //if array lengths aren't equal yet, continue	

						}
						else{ //different voice incoming next; unset array; begin from scratch
								$lastVoice = $part->measure[$i]->note[$j]->voice;
								$j--;
								self::$xmlArray = array(); 
								self::$xmlPositionArray = array();
							}
					}
				}
			}//end of foreach(parts as part)

			// check if result->occ is empty
			if(!empty(self::$result->occurences)){
				//push result
				array_push(self::$results, self::$result);
			}

		});


return self::$results;

// echo "<br>";
// var_dump(self::$results);
// 		if(empty(self::$results)){

// 		echo "<br>result is empty!";
// 		}
// echo "<hr>";

// bla();

	}
}
