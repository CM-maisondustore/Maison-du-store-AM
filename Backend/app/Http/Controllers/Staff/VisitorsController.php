<?php
namespace App\Http\Controllers\Staff;

use App\Models\{SiteVisit, SiteVisitEvent};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisitorsController
{
    public function index(Request $request)
    {
        $since = now()->subDays(30);
        $live = SiteVisit::where('last_activity_at', '>=', now()->subMinutes(2))->orderByDesc('last_activity_at')->limit(100)->get();
        if ($request->boolean('live')) return response()->json([
            'count' => $live->count(),
            'html' => view('staff.visitors-live', compact('live'))->render(),
        ])->header('Cache-Control', 'no-store');
        $daily = SiteVisit::where('arrived_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(arrived_at) as day, COUNT(*) as visits, COUNT(DISTINCT visitor_key) as visitors')->groupBy('day')->orderBy('day')->get();
        $eventNames = ['form_start','form_submit','quote_received','appointment_received','whatsapp_click','quote_click','appointment','product_view'];
        $events = SiteVisitEvent::whereIn('name', $eventNames)->where('created_at','>=',$since)->select('name', DB::raw('COUNT(*) as total'))->groupBy('name')->pluck('total','name');
        $abandoned = SiteVisitEvent::where('name','form_start')->where('created_at','<',now()->subMinutes(10))->where('created_at','>=',$since)
            ->whereNotExists(function ($q) { $q->selectRaw('1')->from('site_visit_events as completed')->whereColumn('completed.site_visit_id','site_visit_events.site_visit_id')->where('completed.name','form_submit')->whereColumn('completed.created_at','>=','site_visit_events.created_at'); })->count();
        $origins = SiteVisit::where('arrived_at','>=',$since)->select('origin',DB::raw('COUNT(*) as total'))->groupBy('origin')->orderByDesc('total')->get();
        $pages = SiteVisitEvent::where('name','page_view')->where('created_at','>=',$since)->select('path',DB::raw('COUNT(*) as total'))->groupBy('path')->orderByDesc('total')->limit(10)->get();
        $returning = SiteVisit::where('arrived_at','>=',$since)->select('visitor_key',DB::raw('COUNT(*) as visits'))->groupBy('visitor_key')->havingRaw('COUNT(*) > 1')->count();
        $countries = SiteVisit::where('arrived_at','>=',$since)->whereNotNull('country_code')->select('country_code',DB::raw('COUNT(*) as total'))->groupBy('country_code')->orderByDesc('total')->limit(8)->get();
        $campaigns = SiteVisit::where('arrived_at','>=',$since)->whereNotNull('campaign')->select('campaign',DB::raw('COUNT(*) as total'))->groupBy('campaign')->orderByDesc('total')->limit(10)->get();
        $pageViews = SiteVisit::where('arrived_at','>=',$since)->sum('page_views');
        $todayEvents = SiteVisitEvent::whereDate('created_at',today())->whereIn('name',['form_submit','quote_received','appointment_received'])->count();
        $todayVisits = SiteVisit::whereDate('arrived_at',today())->count();
        $yesterdayVisits = SiteVisit::whereDate('arrived_at',today()->subDay())->count();
        $trafficSpike = $todayVisits >= 10 && $todayVisits >= max(1, $yesterdayVisits) * 2;
        return view('staff.visitors', compact('live','daily','events','abandoned','origins','pages','returning','countries','campaigns','todayEvents','todayVisits','trafficSpike','pageViews'));
    }
}
