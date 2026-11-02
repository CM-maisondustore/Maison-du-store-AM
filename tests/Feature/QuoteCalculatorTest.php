<?php
namespace Tests\Feature;

use App\Services\Quotes\{QuoteCalculator,QuoteFinancials};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class QuoteCalculatorTest extends TestCase
{
    private function opening(string $width='2', string $fabric='5', int $count=1): array
    {
        return ['label'=>'Fenêtre','width'=>$width,'height'=>'2.1','quantity'=>$count,'lines'=>[
            ['description'=>'Rail simple','unit'=>'m','quantityMode'=>'width','unitPrice'=>5000],
            ['description'=>'Tissu occultant','unit'=>'m','quantity'=>$fabric,'unitPrice'=>9000],
            ['description'=>'Reflet','unit'=>'m','quantity'=>$fabric,'unitPrice'=>900],
            ['description'=>'Confection','unit'=>'m','quantity'=>$fabric,'unitPrice'=>1000],
        ]];
    }
    public function test_required_three_openings_example_and_half_up_deposit(): void
    {
        $input=['openings'=>[$this->opening(count:2),[...$this->opening(),'height'=>'2.8'],[...$this->opening('1.5','3.75'),'height'=>'2.6']],
            'fees'=>[['description'=>'Pose et livraison','unitPrice'=>30000]]];
        $before=$input; $result=(new QuoteCalculator)->calculate($input);
        $this->assertSame($before,$input,'Input must stay immutable');
        $this->assertSame([129000,64500,48375],array_column($result['openings'],'subtotal'));
        $this->assertSame(64500,$result['openings'][0]['unitSubtotal']);
        foreach (['suppliesAndConfectionTotal'=>241875,'feesTotal'=>30000,'grossTotal'=>271875,'finalTotal'=>271875,'deposit'=>190313,'balance'=>81562] as $key=>$expected) $this->assertSame($expected,$result[$key],$key);
        $this->assertSame($result['finalTotal'],$result['deposit']+$result['balance']);
        $individual=(new QuoteCalculator)->calculate([...$input,'displayMode'=>'individual']);
        $this->assertSame($result['finalTotal'],$individual['finalTotal']);
        $this->assertCount(4,QuoteFinancials::display($individual));
        $this->assertSame(241875,array_sum(array_column(QuoteFinancials::display($individual),'subtotal')));
    }
    public function test_six_stores_use_area_and_a_single_global_fee(): void
    {
        $r=(new QuoteCalculator)->calculate(['openings'=>[['width'=>'1.8','height'=>'1.5','quantity'=>6,'lines'=>[['unit'=>'m2','quantityMode'=>'area','unitPrice'=>25000]]]],'fees'=>[['unitPrice'=>30000]]]);
        $this->assertSame('2.7',$r['openings'][0]['unitArea']); $this->assertSame('16.2',$r['openings'][0]['totalArea']);
        $this->assertSame(405000,$r['suppliesAndConfectionTotal']);$this->assertSame(30000,$r['feesTotal']);
    }
    #[DataProvider('discountExamples')]
    public function test_discounts(int $gross,array $discount,int $amount,int $final,int $deposit,int $balance): void
    {
        $r=(new QuoteCalculator)->calculate(['openings'=>[['width'=>1,'height'=>1,'lines'=>[['unit'=>'flat','unitPrice'=>$gross]]]],'discount'=>$discount]);
        foreach (['grossTotal'=>$gross,'discountAmount'=>$amount,'finalTotal'=>$final,'deposit'=>$deposit,'balance'=>$balance] as $key=>$expected) $this->assertSame($expected,$r[$key]);
        $this->assertSame($gross,$r['openings'][0]['lines'][0]['unitPrice']);
    }
    public static function discountExamples(): array { return [
        [500000,['type'=>'percentage','value'=>10],50000,450000,315000,135000],
        [579000,['type'=>'target_total','value'=>515000],64000,515000,360500,154500],
        [500000,['type'=>'fixed','value'=>10000],10000,490000,343000,147000],
    ]; }
    public function test_manual_quantity_takes_priority_and_decimal_meters_remain_exact(): void
    {
        $input=['openings'=>[['width'=>5,'height'=>2,'lines'=>[['unit'=>'m','quantity'=>'12','quantityMode'=>'coefficient','coefficient'=>'2.5','unitPrice'=>9000]]]]];
        $r=(new QuoteCalculator)->calculate($input);$this->assertSame('12',$r['openings'][0]['lines'][0]['quantity']);$this->assertSame('manual',$r['openings'][0]['lines'][0]['quantitySource']);
        $input['openings'][0]['lines'][0]['quantity']='6.625';$r=(new QuoteCalculator)->calculate($input);$this->assertSame(59625,$r['finalTotal']);
        unset($input['openings'][0]['lines'][0]['quantity']);$r=(new QuoteCalculator)->calculate($input);$this->assertSame('12.5',$r['openings'][0]['lines'][0]['quantity']);
    }
    public function test_double_rail_motor_units_and_explicit_fee_scopes(): void
    {
        $input=['openings'=>[['width'=>2,'height'=>2.5,'quantity'=>2,'lines'=>[
            ['unit'=>'m','quantityMode'=>'width','unitPrice'=>10000],['unit'=>'piece','quantity'=>1,'unitPrice'=>100000],['unit'=>'flat','quantity'=>1,'unitPrice'=>20000]]]],
            'fees'=>[['unitPrice'=>30000,'scope'=>'global'],['unitPrice'=>5000,'scope'=>'per_opening'],['unitPrice'=>2000,'scope'=>'per_trip','quantity'=>3],['unitPrice'=>0]]];
        $r=(new QuoteCalculator)->calculate($input);$this->assertSame(280000,$r['suppliesAndConfectionTotal']);$this->assertSame(46000,$r['feesTotal']);
    }
    public function test_payment_authorization_depends_on_received_amount(): void
    {
        $input=['openings'=>[$this->opening()],'receivedAmount'=>0];$r=(new QuoteCalculator)->calculate($input);
        $this->assertFalse($r['productionAuthorized']);$this->assertSame('pending_deposit',$r['paymentStatus']);
        $r=(new QuoteCalculator)->calculate([...$input,'receivedAmount'=>45150]);$this->assertTrue($r['productionAuthorized']);$this->assertSame('deposit_paid',$r['paymentStatus']);
        $r=(new QuoteCalculator)->calculate([...$input,'receivedAmount'=>64500]);$this->assertSame('fully_paid',$r['paymentStatus']);
    }
    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected(array $input): void
    {
        $this->expectException(\InvalidArgumentException::class); (new QuoteCalculator)->calculate($input);
    }
    public static function invalidValues(): array
    {
        $o=['width'=>2,'height'=>2,'lines'=>[['unit'=>'m','quantity'=>1,'unitPrice'=>5000]]];$result=[];
        foreach (['width'=>-1,'height'=>0,'quantity'=>0] as $key=>$value) $result[]=[['openings'=>[[...$o,$key=>$value]]]];
        foreach (['quantity'=>-1,'unitPrice'=>-1,'unit'=>'unknown'] as $key=>$value) $result[]=[['openings'=>[[...$o,'lines'=>[[...$o['lines'][0],$key=>$value]]]]]];
        foreach (['NaN','Infinity','1e309','1,2,3'] as $value) $result[]=[['openings'=>[[...$o,'width'=>$value]]]];
        foreach ([['type'=>'percentage','value'=>101],['type'=>'fixed','value'=>5001],['type'=>'target_total','value'=>5001],['type'=>'fixed','value'=>'2.5']] as $d) $result[]=[['openings'=>[$o],'discount'=>$d]];
        $result[]=[['openings'=>[$o],'depositPercent'=>101]];$result[]=[['openings'=>[$o],'receivedAmount'=>5001]];
        $result[]=[['openings'=>[[...$o,'lines'=>[['unit'=>'lot100','quantity'=>'1.5','unitPrice'=>2000]]]]]];
        return $result;
    }
}
