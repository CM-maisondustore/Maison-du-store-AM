<?php

namespace Tests;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    protected function agentPayload(array $data): array
    {
        $selection=function(array $s): array {
            $store=($s['category'] ?? '')==='store';$finish=\App\Models\QuoteOption::find($s['finish_id'] ?? null);
            $code=match($finish?->code){'wave'=>'wave-manuel','standard'=>'store-standard',default=>$finish?->code};
            return ['project_type'=>$store?'store':(!empty($s['sheer_id'])?(empty($s['curtain_id'])?'voilage':'rideau_voilage'):'rideau'),'motorized'=>$s['motorized'] ?? 0,'finish_id'=>\App\Models\QuoteOption::where('code',$code)->value('id'),'zone'=>'abidjan'];
        };
        return ['submission_token'=>$data['submission_token'],'edit_version'=>$data['edit_version'],'contact'=>$data['contact'],'defaults'=>$selection($data['defaults']),
            'windows'=>array_map(fn($w)=>[...$selection($w),...collect($w)->only(['inherit_defaults','label','width','height','notes'])->all()],$data['windows']),'field_notes'=>$data['field_notes'] ?? 'Observations du terrain'];
    }
    protected function seedProducts(): void
    {
        $this->seed();
        foreach (json_decode(file_get_contents(database_path('data/products.json')),true) as $details) {
            $reference=$details['reference']; unset($details['id'],$details['reference']);
            \App\Models\Product::firstOrCreate(['reference'=>$reference],['details'=>$details]);
        }
    }
}
