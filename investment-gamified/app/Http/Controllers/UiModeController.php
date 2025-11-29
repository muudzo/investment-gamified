<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UiModeController extends Controller
{
    /**
     * Toggle between normal and senior UI modes
     */
    public function toggleUiMode(Request $request)
    {
        // Get current mode from session, default to 'normal'
        $currentMode = $request->session()->get('ui_mode', 'normal');
        
        // Toggle the mode
        $newMode = $currentMode === 'senior' ? 'normal' : 'senior';
        
        // Store in session
        $request->session()->put('ui_mode', $newMode);
        
        // Redirect back to home
        return redirect('/');
    }
    
    /**
     * Set UI mode explicitly
     */
    public function setUiMode(Request $request, $mode)
    {
        // Validate mode
        if (!in_array($mode, ['normal', 'senior'])) {
            return redirect('/');
        }
        
        // Store in session
        $request->session()->put('ui_mode', $mode);
        
        // Redirect back to home
        return redirect('/');
    }
}
