<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    /**
     * @param  bool  $bare  Render the page full width instead of inside the reading card.
     */
    public function __construct(public bool $bare = false) {}

    public function render(): View
    {
        return view('layouts.public');
    }
}
