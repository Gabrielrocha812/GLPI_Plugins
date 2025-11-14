<?php
include ('../../../inc/includes.php');

Session::checkLoginUser();
Html::header(__('Dashboard de Horas'), '', 'tools', 'dashboard');

// --- INÍCIO DAS MODIFICAÇÕES ---

// 1. Verificar perfil
$is_super_admin = ($_SESSION['glpiactiveprofile']['name'] == 'Super-Admin');

// 2. ID do usuário logado
$logged_user_id = Session::getLoginUserID();
$user_id_to_query = $logged_user_id;

// Se for admin, permite escolher um técnico
if ($is_super_admin && isset($_POST['technician_id']) && $_POST['technician_id'] > 0) {
    $user_id_to_query = intval($_POST['technician_id']);
}

// 3. Lista de técnicos
$technicians = [];
if ($is_super_admin) {
    $technicians = PluginRelatoriotecnicosRelatoriotecnicos::getTechnicians();
}

$date_from = $_POST['date_from'] ?? date('Y-m-01');
$date_to   = $_POST['date_to'] ?? date('Y-m-t');

$hours = PluginRelatoriotecnicosRelatoriotecnicos::getUserHours($user_id_to_query, $date_from, $date_to);
$monthly_stats = PluginRelatoriotecnicosRelatoriotecnicos::getMonthlyStats($user_id_to_query, $date_from, $date_to);

// --- CÁLCULO DE DIAS ÚTEIS ---
function getWorkingDays($startDate, $endDate) {
    try {
        $begin = new DateTime($startDate);
        $end   = new DateTime($endDate);
    } catch (Exception $e) {
        return 0;
    }

    $end = $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($begin, $interval, $end);

    $workingDays = 0;
    foreach ($dateRange as $date) {
        if ($date->format('N') < 6) {
            $workingDays++;
        }
    }
    return $workingDays;
}

$totalWorkingDays = getWorkingDays($date_from, $date_to);

// Converte horas totais (decimal) → segundos
$total_hours_seconds = intval($monthly_stats['total_hours'] * 3600);

// Média diária em segundos
$daily_avg_seconds = ($totalWorkingDays > 0)
    ? intval(($monthly_stats['total_hours'] / $totalWorkingDays) * 3600)
    : 0;

// Função para converter segundos → HH:MM
function formatHours($seconds) {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return sprintf('%02dh %02dm', $h, $m);
}

$formatted_total_hours = formatHours($total_hours_seconds);
$formatted_daily_avg   = formatHours($daily_avg_seconds);

?>

<style>
    .dashboard-container {
        max-width: 1200px;
        margin: 30px auto;
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    }
    h2 {
        font-size: 1.8rem;
        text-align: center;
        margin-bottom: 25px;
        color: #333;
    }
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: #182836;
        color: white;
        padding: 25px;
        border-radius: 10px;
        text-align: center;
    }
    .stat-card.secondary { background: #D75A31; }
    .stat-card.success { background: #fff; color: #182836; }
    form {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 25px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    th, td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
    th { background: #f8f9fa; }
    tr:hover { background: #f2f8ff; }
</style>

<div class="dashboard-container">

    <h2>⏱️ Dashboard de Horas Trabalhadas</h2>

    <div class="stats-cards">
        <div class="stat-card">
            <h3>Total de Horas no Período</h3>
            <p class="value"><?= $formatted_total_hours ?></p>
        </div>

        <div class="stat-card secondary">
            <h3>Média Diária (Dias Úteis)</h3>
            <p class="value"><?= $formatted_daily_avg ?></p>
        </div>

        <div class="stat-card success">
            <h3>Total de Tickets</h3>
            <p class="value"><?= $monthly_stats['total_tickets'] ?></p>
        </div>

        <div class="stat-card" style="background:#FFF;color:#D75A31;">
            <h3>Total de Tarefas</h3>
            <p class="value"><?= $monthly_stats['total_tarefas'] ?></p>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">

        <?php if ($is_super_admin): ?>
        <label>Técnico:</label>
        <select name="technician_id">
            <option value="0">-- Selecione --</option>
            <?php foreach ($technicians as $technician): ?>
                <option value="<?= $technician['id'] ?>" <?= ($user_id_to_query == $technician['id']) ? 'selected' : '' ?>>
                    <?= Html::clean($technician['fullname']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <label>De:
            <input type="date" name="date_from" value="<?= Html::cleanInputText($date_from) ?>">
        </label>

        <label>Até:
            <input type="date" name="date_to" value="<?= Html::cleanInputText($date_to) ?>">
        </label>

        <input type="submit" value="Filtrar">
    </form>

<?php if (!empty($hours)) : ?>
<div class="table-responsive">
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Data</th>
    <th>Ticket</th>
    <th>Tempo Total (HH:MM)</th>
</tr>
</thead>
<tbody>

<?php foreach ($hours as $line): ?>
    <?php
        $sec = intval($line['total_time']);
        $formatted = formatHours($sec);
    ?>
    <tr>
        <td><?= Html::clean($line['ticket_id']) ?></td>
        <td><?= date("d/m/Y", strtotime($line['task_date'])) ?></td>
        <td>
            <a href="<?= $CFG_GLPI['url_base'] ?>/front/ticket.form.php?id=<?= $line['ticket_id'] ?>">
                <?= Html::clean($line['ticket_name']) ?>
            </a>
        </td>
        <td><?= $formatted ?></td>
    </tr>
<?php endforeach; ?>

</tbody>
</table>
</div>

<?php else: ?>
<p style="text-align:center;color:#888;">Nenhum registro encontrado.</p>
<?php endif; ?>

</div>

<?php Html::footer(); ?>
