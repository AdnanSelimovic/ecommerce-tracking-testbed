<?php
namespace App\Models;
use App\Enums\ServerTrackingDispatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class ServerTrackingDispatch extends Model {
 protected $fillable=['ground_truth_event_id','experiment_run_id','provider','status','target_id','api_version','queued_at','first_attempted_at','accepted_at','failed_at','attempt_count','last_http_status','last_error_code','last_error_message','request_payload','response_payload'];
 protected function casts(): array { return ['status'=>ServerTrackingDispatchStatus::class,'queued_at'=>'datetime','first_attempted_at'=>'datetime','accepted_at'=>'datetime','failed_at'=>'datetime','request_payload'=>'array','response_payload'=>'array']; }
 public function groundTruthEvent(): BelongsTo{return $this->belongsTo(GroundTruthEvent::class);}
 public function experimentRun(): BelongsTo{return $this->belongsTo(ExperimentRun::class);}
 public function attempts(): HasMany{return $this->hasMany(ServerTrackingAttempt::class);}
}
