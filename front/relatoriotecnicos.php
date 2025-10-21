<?php
include ('../../../inc/includes.php');

Session::checkLoginUser();
Html::header(__('Dashboard de Horas'), '', 'tools', 'dashboard');

// --- INÍCIO DAS MODIFICAÇÕES ---

// 1. Verificar se o usuário logado tem perfil de Super-Admin
// IMPORTANTE: Altere 'Super-Admin' para o nome exato do seu perfil de super administrador.
$is_super_admin = ($_SESSION['glpiactiveprofile']['name'] == 'Super-Admin');

// 2. Determinar qual ID de usuário deve ser usado para a consulta
$logged_user_id = Session::getLoginUserID(); // ID do usuário logado
$user_id_to_query = $logged_user_id;        // Por padrão, mostra os dados do próprio usuário

// Se for Super-Admin e tiver selecionado um técnico no filtro, usa o ID selecionado
if ($is_super_admin && isset($_POST['technician_id']) && $_POST['technician_id'] > 0) {
    $user_id_to_query = intval($_POST['technician_id']);
}

// 3. Se for Super-Admin, busca a lista de técnicos para o dropdown
$technicians = [];
if ($is_super_admin) {
    $technicians = PluginRelatoriotecnicosRelatoriotecnicos::getTechnicians();
}

// O resto do código continua, mas usando a variável $user_id_to_query

$date_from = $_POST['date_from'] ?? date('Y-m-01');
$date_to   = $_POST['date_to'] ?? date('Y-m-t');

// MUDANÇA AQUI: passe a variável $user_id_to_query para as funções
$hours = PluginRelatoriotecnicosRelatoriotecnicos::getUserHours($user_id_to_query, $date_from, $date_to);
$monthly_stats = PluginRelatoriotecnicosRelatoriotecnicos::getMonthlyStats($user_id_to_query, $date_from, $date_to);

// --- FIM DAS MODIFICAÇÕES ---

// --- CÁLCULO DE DIAS ÚTEIS PARA MÉDIA DIÁRIA ---

/**
 * Calcula o número de dias úteis (Seg-Sex) entre duas datas (inclusivo).
 * @param string $startDate Data inicial (Y-m-d)
 * @param string $endDate Data final (Y-m-d)
 * @return int O número de dias úteis
 */
function getWorkingDays($startDate, $endDate) {
    try {
        $begin = new DateTime($startDate);
        $end   = new DateTime($endDate);
    } catch (Exception $e) {
        return 0; // Retorna 0 em caso de data inválida
    }

    // Adiciona 1 dia ao final para que o DatePeriod inclua o último dia
    $end = $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($begin, $interval, $end);

    $workingDays = 0;
    foreach ($dateRange as $date) {
        $dayOfWeek = $date->format('N'); // 'N' retorna 1 (Seg) a 7 (Dom)
        if ($dayOfWeek < 6) { // Conta apenas de 1 (Seg) a 5 (Sex)
            $workingDays++;
        }
    }
    return $workingDays;
}

// 1. Calcula o total de dias úteis no período selecionado
$totalWorkingDays = getWorkingDays($date_from, $date_to);

// 2. Recalcula a média diária com base nos dias úteis
$daily_average_working_days = 0; // Define um valor padrão
if ($totalWorkingDays > 0) {
    // Usa o total de horas já buscado pela função original
    $daily_average_working_days = $monthly_stats['total_hours'] / $totalWorkingDays;
}

// --- FIM DO CÁLCULO DE DIAS ÚTEIS ---

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
        font-weight: 600;
        color: #333;
        margin-bottom: 25px;
        text-align: center;
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
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 1rem;
        opacity: 0.9;
    }

    .stat-card .value {
        font-size: 2rem;
        font-weight: bold;
        margin: 0;
    }

    .stat-card.secondary {
        background: #D75A31;
    }

    .stat-card.success {
        background: #fff;
        color: #182836;
    }

    form {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
        margin-bottom: 25px;
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
    }

    input[type="date"] {
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 8px 12px;
    }

    input[type="submit"] {
        background: #007bff;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 8px 18px;
        cursor: pointer;
        transition: background 0.2s ease;
    }

    input[type="submit"]:hover {
        background: #0056b3;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }

    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    th {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    tr:hover {
        background-color: #f2f8ff;
    }

    .chart-container {
        margin: 30px 0;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    @media (max-width: 768px) {
        .dashboard-container {
            padding: 20px;
        }
        form {
            flex-direction: column;
            align-items: stretch;
        }
        input[type="submit"] {
            width: 100%;
        }
        .stats-cards {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dashboard-container">
    <h2>⏱️ Dashboard de Horas Trabalhadas</h2>

    <div class="stats-cards">
        <div class="stat-card">
            <h3>Total de Horas no Período</h3>
            <p class="value"><?= number_format($monthly_stats['total_hours'], 2) ?>h</p>
        </div>
        <div class="stat-card secondary">
            <h3>Média Diária (Dias Úteis)</h3>
            <p class="value"><?= number_format($daily_average_working_days, 2) ?>h</p>
        </div>
        <div class="stat-card success">
            <h3>Total de Tickets</h3>
            <p class="value"><?= $monthly_stats['total_tickets'] ?></p>
        </div>
        <div class="stat-card" style="background: #FFF;
    color: #D75A31;">
            <h3>Total de Tarefas</h3>
            <p class="value"><?= $monthly_stats['total_tarefas'] ?></p>
        </div>
    </div>

    <form method="post" action="">
    <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">

    <?php if ($is_super_admin): ?>
        <label for="technician_id">Técnico:</label>
        <select name="technician_id" id="technician_id">
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
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>  <th>Data</th>
                        <th>Ticket</th>
                        <th>Tempo Total (horas)</th>
                    </tr>
                </thead>
<tbody>
    <?php foreach ($hours as $hour): ?>
        <tr>
            <td><?= Html::clean($hour['ticket_id']) ?></td>
            <td>
                <?= date("d/m/Y", strtotime($hour['task_date'])) ?>
            </td>
            <td>
                <a href="<?= $CFG_GLPI['url_base'] ?>/front/ticket.form.php?id=<?= $hour['ticket_id'] ?>">
                    <?= Html::clean($hour['ticket_name']) ?>
                </a>
            </td>
            <td><?= number_format(round($hour['total_time'] / 3600, 2), 2, ',', '.') ?>h</td>
        </tr>
    <?php endforeach; ?>
</tbody>
            </table>
        </div>
    <?php else : ?>
        <p style="text-align:center; color:#888;">Nenhum registro encontrado para o período selecionado.</p>
    <?php endif; ?>
</div>

<?php
Html::footer();
?>