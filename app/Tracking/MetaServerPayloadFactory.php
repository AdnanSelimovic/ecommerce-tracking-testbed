<?php
namespace App\Tracking;
use App\Models\GroundTruthEvent;
class MetaServerPayloadFactory { public function __construct(private ClientTrackingPayloadFactory $base,private MetaClientEventMapper $mapper){} public function make(GroundTruthEvent $e,array $context=[]):array{$p=$this->base->make($e);$m=$this->mapper->map($p);$user=array_filter(['client_user_agent'=>$context['client_user_agent']??null,'fbp'=>$context['fbp']??null,'fbc'=>$context['fbc']??null]);return ['data'=>[['event_name'=>$m['name'],'event_time'=>$e->occurred_at->timestamp,'event_id'=>$e->event_id,'action_source'=>'website','user_data'=>$user,'custom_data'=>$m['params']]],'test_event_code'=>$context['test_event_code']??null];} }
