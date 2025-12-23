<?php

namespace App\Http\Controllers;

use App\Models\UserTutorial;
use Illuminate\Http\Request;

class TutorialController extends Controller
{
    /**
     * Marca un tutorial como completado.
     */
    public function complete(Request $request)
    {
        $request->validate([
            'module_name' => 'required|string|max:255',
        ]);

        $user = $request->user();

        UserTutorial::updateOrCreate(
            [
                'user_id' => $user->id,
                'module_name' => $request->module_name,
            ],
            [
                'is_completed' => true,
                'completed_at' => now(),
            ]
        );

        return response()->json(['status' => 'success']);
    }

    /**
     * Verifica el estado de un tutorial específico.
     */
    public function check(Request $request, string $moduleName)
    {
        $completed = $request->user()->hasCompletedTutorial($moduleName);
        return response()->json(['completed' => $completed]);
    }
}