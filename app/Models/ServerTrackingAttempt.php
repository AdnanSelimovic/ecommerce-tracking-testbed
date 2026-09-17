<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ServerTrackingAttempt extends Model {
 protected $fillable=['server_tracking_dispatch_id','attempt_number','started_at','finished_at','latency_ms','http_status','outcome','response_payload','error_message'];
 protected function casts(): array{return ['started_at'=>'datetime','finished_at'=>'datetime','response_payload'=>'array'];}
 public function dispatch(): BelongsTo{return $this->belongsTo(ServerTrackingDispatch::class,'server_tracking_dispatch_id');}
}
