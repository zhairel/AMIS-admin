<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SchoolYearService;
use Illuminate\Http\Request;

class SchoolYearSwitchController extends Controller
{
    public function __invoke(Request $request, SchoolYearService $schoolYearService)
    {
        $schoolYear = $request->input('school_year');
        if (! is_string($schoolYear) || ! $schoolYearService->isValidFormat($schoolYear)) {
            return redirect()->back()->with('error', 'Invalid school year format.');
        }

        $cleanYear = trim($schoolYear);
        $schoolYearService->set($cleanYear);

        $returnUrl = $request->input('return_url');
        if (! empty($returnUrl)) {
            $parsed = parse_url($returnUrl);
            $query = [];
            if (! empty($parsed['query'])) {
                parse_str($parsed['query'], $query);
            }
            $query['school_year'] = $cleanYear;

            $scheme = isset($parsed['scheme']) ? $parsed['scheme'].'://' : '';
            $host = $parsed['host'] ?? '';
            $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
            $user = $parsed['user'] ?? '';
            $pass = isset($parsed['pass']) ? ':'.$parsed['pass'] : '';
            $pass = ($user || $pass) ? "$pass@" : '';
            $path = $parsed['path'] ?? '';
            $newQuery = '?'.http_build_query($query);
            $fragment = isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';

            $targetUrl = "{$scheme}{$user}{$pass}{$host}{$port}{$path}{$newQuery}{$fragment}";

            return redirect()->to($targetUrl)->with('success', "Active School Year switched to S.Y. {$cleanYear}");
        }

        return redirect()->back()->with('success', "Active School Year switched to S.Y. {$cleanYear}");
    }
}
