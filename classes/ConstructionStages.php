<?php

class ConstructionStages
{
	private $db;

	public function __construct()
	{
		$this->db = Api::getDb();
	}

	/**
	 * @return [json]
	 */
	public function getAll()
	{
		$stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
		");
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * @param mixed $id
	 * 
	 * @return [json]
	 */
	public function getSingle($id)
	{
		$stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
			WHERE ID = :id
		");
		$stmt->execute(['id' => $id]);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * @param ConstructionStagesCreate $data
	 * 
	 * @return [json]
	 */
	public function post(ConstructionStagesCreate $data)
	{
		$stmt = $this->db->prepare("
			INSERT INTO construction_stages
			    (name, start_date, end_date, duration, durationUnit, color, externalId, status)
			    VALUES (:name, :start_date, :end_date, :duration, :durationUnit, :color, :externalId, :status)
			");

		$durationUnit = $this->validUnit($data->durationUnit);	
		$duration = $this->duration($data->startDate,$data->endDate,$durationUnit);

		$stmt->execute([
			'name' => $data->name,
			'start_date' => $data->startDate,
			'end_date' => $data->endDate,
			'duration' => $duration,
			'durationUnit' => $durationUnit,
			'color' => $data->color,
			'externalId' => $data->externalId,
			'status' => $data->status,
		]);
		return $this->getSingle($this->db->lastInsertId());
	}
	
	/**
	 * @param mixed $start
	 * @param mixed $end
	 * @param mixed $unit
	 * 
	 * @return [string]
	 */
	function duration($start, $end, $unit){
		if($end !=  '' OR $start != ''){
			$date1=$start;
			$date2=$end;
			$diff = abs(strtotime($date2) - strtotime($date1));
			switch ($unit) {
				case "HOURS":
				  return floor(($diff)/ (60*60));
				 
				case "DAYS":
				  return floor(($diff)/ (60*60*24));
				  
				case "WEEKS":
				  return floor(($diff)/ (60*60*24*7));
			
				default:
				  return floor(($diff)/ (60*60*24));
			  }
		}else{
			return null;
		}
		
	}
	/**
	 * @param mixed $dateStr
	 * 
	 * @return [bool]
	 */
	function ISO8601Date($dateStr) {
		if (preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(\.\d+)?(([+-]\d\d:\d\d)|Z)?$/', $dateStr) > 0) {
			return true;
		} else {
			return false;
		}
	}
	/**
	 * @param mixed $start
	 * @param mixed $end
	 * 
	 * @return [bool]
	 */
	function diffDate($start, $end){
		$date1=date_create($start);
		$date2=date_create($end);
		$diff=date_diff($date1,$date2);
		if($diff->format("%R%a") > 0){	
			return true;
		} else {
			return false;
		}
	}
	/**
	 * @param mixed $data
	 * 
	 * @return [bool]
	 */
	function HEXcolor($data){
		if (preg_match('/^#[a-f0-9]{6}$/i', $data) > 0) {
			return true;
		} else {
			return false;
		}
	}
	/**
	 * @param mixed $data
	 * 
	 * @return [string]
	 */
	function validStatus($data){
		switch ($data) {
			case "NEW":
			  return $data;
			 
			case "DELETED":
			  return $data;
			  
			case "PLANNED":
			  return $data;
		
			default:
			  return "NEW";
		  }
	}
	/**
	 * @param mixed $data
	 * 
	 * @return [string]
	 */
	function validUnit($data){
		switch ($data) {
			case "HOURS":
			  return $data;
			 
			case "DAYS":
			  return $data;
			  
			case "WEEKS":
			  return $data;
		
			default:
			  return "DAYS";
		  }
	}

	/**
	 * @param ConstructionStagesUpdate $data
	 * @param mixed $id
	 * 
	 * @return [string]
	 */
	public function patch(ConstructionStagesUpdate $data, $id)
	{
		$oldData = $this->getSingle($id)[0];
		$err = [];
		
		if(isset($data->name)){
			if(strlen($data->name) <= 255){
				$name = $data->name;
			}else{
				$err[] = 'Name must be a maximum of 255 characters';
			}
		}else{
			$name = $oldData['name'];
		}
		
		if(isset($data->startDate)){
			if($this->ISO8601Date($data->startDate)){
				$startDate = $data->startDate;
			}else{
				$startDate = '';
				$err[] = 'start_date is a valid date&time in iso8601 format';
			}
		}else{
			$startDate = $oldData['startDate'];
		}

		if(isset($data->endDate)){
			if((($this->ISO8601Date($data->endDate) && $this->diffDate($data->startDate,$data->endDate)) || $data->endDate == null)){
				$endDate = $data->endDate;
			}else{
				$endDate = '';
				$err[] = 'end_date is either `null` or a valid datetime which is later than the start_date';
			}
		}else{
			$endDate = $oldData['endDate'];
		}

		if(isset($data->durationUnit)){
			$durationUnit = $this->validUnit($data->durationUnit);
		}else{
			$durationUnit = $oldData['durationUnit'];
		}
		
		$duration = $this->duration($startDate,$endDate,$durationUnit);

		
		if(isset($data->color)){
			if(($this->HEXcolor($data->color) || $data->color == null )){
				$color = $data->color;
			}else{
				$err[] = 'color is either `null` or a valid HEX color i.e. #FF0000';
			}
		}else{
			$color = $oldData['color'];
		}

		if(isset($data->externalId)){
			if((strlen($data->externalId) <= 255 || $data->externalId == null)){
				$externalId = $data->externalId;
			}else{
				$err[] = 'externalId is `null` or any string up to 255 characters in length';
			}
		}else{
			$externalId = $oldData['externalId'];
		}

		if(isset($data->status)){	
			$status = $this->validStatus($data->status);
		}else{
			$status = $oldData['status'];
		}

		if(empty($err)){
			$stmt = $this->db->prepare("
			UPDATE construction_stages SET name=:name, start_date=:start_date, end_date=:end_date, duration=:duration, durationUnit=:durationUnit, color=:color, externalId=:externalId, status=:status WHERE id=:id");
			
	
			$stmt->execute([
				'name' => $name,
				'start_date' => $startDate,
				'end_date' => $endDate,
				'duration' => $duration,
				'durationUnit' => $durationUnit,
				'color' => $color,
				'externalId' => $externalId,
				'status' => $status,
				'id' => $id,
			]);
			
			if($stmt->execute()){
				return $this->getSingle($id);
			}else{
				return "Database problem, please contact site admin";
			}
		}else{
				return $err;
		}
		
	}

	/**
	 * @param mixed $id
	 * 
	 * @return [string]
	 */
	public function delete($id){
		$stmt = $this->db->prepare("UPDATE construction_stages SET status='DELETE' where id=:id");
		if($stmt->execute(['id' => $id])){
			return "Successfully deleted";
		}

	}
}