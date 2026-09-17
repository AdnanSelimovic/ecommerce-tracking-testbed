<?php
namespace App\Enums;
enum ServerTrackingDispatchStatus: string { case Pending = 'pending'; case Processing = 'processing'; case EndpointAccepted = 'endpoint_accepted'; case Failed = 'failed'; }
