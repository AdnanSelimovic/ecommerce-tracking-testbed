<?php
namespace App\Tracking;
use App\Models\ExperimentRun; use App\Models\GroundTruthEvent;
class Ga4ServerPayloadFactory { public function __construct(private ClientTrackingPayloadFactory $base,private Ga4ClientEventMapper $mapper){} public function clientId(ExperimentRun $run): string{$h=hash('sha256',$run->run_id);return ((int)(hexdec(substr($h,0,7))%900000000)+100000000).'.'.((int)(hexdec(substr($h,7,7))%900000000)+100000000);} public function make(GroundTruthEvent $e):array{$p=$this->base->make($e);$event=$this->mapper->map($p);$event['params']['tracking_channel']='server';$event['params']['session_id']=$e->experimentRun->started_at->timestamp;return ['client_id'=>$this->clientId($e->experimentRun),'timestamp_micros'=>$e->occurred_at->getTimestamp()*1000000,'events'=>[$event]];} }
