@include('layouts.app', ['title' => $title ?? null, 'siteConfig' => $siteConfig ?? null, 'slot' => $slot, 'authFooter' => $authFooter ?? null])
