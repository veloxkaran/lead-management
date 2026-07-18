<?php

namespace App\Http\Controllers;

use App\Services\OrganizationHierarchyService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrgTreeController extends Controller
{
    public function __construct(protected OrganizationHierarchyService $hierarchy) {}

    public function index(Request $request): View
    {
        $this->authorize('viewOrgTree');

        $viewer = $request->user();

        if ($viewer->isOverseer()) {
            // SuperAdmin sees the complete cross-company hierarchy (matches
            // the existing BelongsToCompany bypass pattern elsewhere);
            // Manager (also an overseer today) sees their own company's tree.
            $companyId = $viewer->isSuperAdmin() ? null : $viewer->company_id;
            $tree = $this->hierarchy->getOrganizationTree($companyId);
        } else {
            $tree = $this->hierarchy->getOrganizationTree($viewer->company_id, $viewer->id);
        }

        return view('org-tree.index', ['tree' => $tree]);
    }
}
