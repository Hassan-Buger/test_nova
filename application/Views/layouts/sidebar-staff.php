<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$nav = [
    ['href' => '/staff/dashboard', 'label' => 'Dashboard', 'icon' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
    ['href' => '/staff/clients', 'label' => 'Clients', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['href' => '/staff/documents', 'label' => 'Documents', 'icon' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M9 14h6M9 18h4"/>'],
    ['href' => '/staff/signatures', 'label' => 'Signatures', 'icon' => '<path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>'],
    ['href' => '/staff/messages', 'label' => 'Messages', 'icon' => '<path d="M21 15a2 2 0 0 1-2 2H8l-4 4V5a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2z"/><path d="M8 8h8M8 12h5"/>'],
    ['href' => '/staff/requests', 'label' => 'Requests', 'icon' => '<path d="M12 3v12M7 8l5-5 5 5"/><path d="M5 15v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/>'],
    ['href' => '/staff/deadlines', 'label' => 'Deadlines', 'icon' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>'],
    ['href' => '/staff/audit', 'label' => 'Audit Log', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>'],
    ['href' => '/staff/users', 'label' => 'User Admin', 'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/><path d="M19 5v4M17 7h4"/>'],
];
?>

<aside id="tnStaffSidebar" class="tn-side staff-sidebar" aria-label="Staff portal navigation">
    <div class="staff-sidebar__brand-row">
        <a class="staff-sidebar__brand tn-sidebar-brand" href="/staff/dashboard" aria-label="TriNova Staff Portal dashboard">
            <img class="tn-brand-logo" src="/assets/images/trinova-staff-portal-logo.png" alt="TriNova Staff Portal">
        </a>
        <button id="tnMobileMenuCloseStaff" class="tn-mobile-close" type="button" aria-label="Close navigation menu" onclick="window.tnToggleMobileMenu ? window.tnToggleMobileMenu(event, false) : null">&times;</button>
    </div>

    <nav aria-label="Primary navigation">
        <?php foreach ($nav as $index => $item): ?>
            <?php
            $active = $currentPath === $item['href'] || ($item['href'] !== '/staff/dashboard' && str_starts_with($currentPath, $item['href'] . '/'));
            $isAdministration = $index === 7;
            ?>
            <?php if ($isAdministration): ?><span class="staff-sidebar__section tn-navlabel">Administration</span><?php endif; ?>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="tn-navitem<?= $active ? ' is-active' : '' ?>" title="<?= htmlspecialchars($item['label']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><?= $item['icon'] ?></svg>
                <span class="tn-navlabel"><?= htmlspecialchars($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="staff-sidebar__logout" action="/logout" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Application\Core\Session::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="tn-navitem" title="Logout">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            <span class="tn-logout-label tn-navlabel">Logout</span>
        </button>
    </form>
</aside>
