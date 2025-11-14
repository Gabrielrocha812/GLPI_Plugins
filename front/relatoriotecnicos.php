<?php
include ('../../../inc/includes.php');

Session::checkLoginUser();
Html::header(__('Dashboard de Horas'), '', 'tools', 'dashboard');

// --- PERFIL DO USUÁRIO / SUPER-ADMIN ---
$is_super_admin = ($_SESSION['glpiactiveprofile']['name'] == 'Super-Admin');

$logged_user_id   = Session::getLoginUserID();
$user_id_to_query = $logged_user_id;

if ($is_super_admin && isset($_POST['technician_id']) && $_POST['technician_id'] > 0) {
    $user_id_to_query = intval($_POST['technician_id']);
}

// Lista de técnicos (para Super-Admin)
$technicians = [];
if ($is_super_admin) {
    $technicians = PluginRelatoriotecnicosRelatoriotecnicos::getTechnicians();
}

// Datas padrão: mês atual
$date_from = $_POST['date_from'] ?? date('Y-m-01');
$date_to   = $_POST['date_to'] ?? date('Y-m-t');

// Busca dados
$hours         = PluginRelatoriotecnicosRelatoriotecnicos::getUserHours($user_id_to_query, $date_from, $date_to);
$monthly_stats = PluginRelatoriotecnicosRelatoriotecnicos::getMonthlyStats($user_id_to_query, $date_from, $date_to);

// --- DIAS ÚTEIS ---
function getWorkingDays($startDate, $endDate) {
    try {
        $begin = new DateTime($startDate);
        $end   = new DateTime($endDate);
    } catch (Exception $e) {
        return 0;
    }

    // Inclui o último dia no intervalo
    $end = $end->modify('+1 day');
    $interval  = new DateInterval('P1D');
    $dateRange = new DatePeriod($begin, $interval, $end);

    $workingDays = 0;
    foreach ($dateRange as $date) {
        $dayOfWeek = $date->format('N'); // 1 = Seg ... 7 = Dom
        if ($dayOfWeek < 6) {
            $workingDays++;
        }
    }
    return $workingDays;
}

$totalWorkingDays = getWorkingDays($date_from, $date_to);

// total_hours vem em HORAS decimais → convertemos para segundos
$total_hours_seconds = isset($monthly_stats['total_hours'])
    ? intval($monthly_stats['total_hours'] * 3600)
    : 0;

// média diária (em segundos) considerando somente dias úteis
$daily_avg_seconds = 0;
if ($totalWorkingDays > 0 && isset($monthly_stats['total_hours'])) {
    $daily_avg_seconds = intval(($monthly_stats['total_hours'] / $totalWorkingDays) * 3600);
}

// Função para formatar segundos → "HHh MMm"
function formatHours($seconds) {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    return sprintf('%02dh %02dm', $h, $m);
}

$formatted_total_hours = formatHours($total_hours_seconds);
$formatted_daily_avg   = formatHours($daily_avg_seconds);

?>

<!-- Tailwind CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          brandDark:  '#182836',
          brandAccent:'#D75A31'
        }
      }
    }
  }
</script>

<div class="min-h-screen bg-slate-100/80 px-4 py-8">
  <div class="max-w-6xl mx-auto space-y-6">

    <!-- Cabeçalho -->
    <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl sm:text-3xl font-semibold text-slate-900 flex items-center gap-2">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-brandDark text-white shadow-sm">
            ⏱️
          </span>
          <span>Dashboard de Horas Trabalhadas</span>
        </h1>
        <p class="text-sm text-slate-500 mt-1">
          Visão geral das horas registradas no período selecionado.
        </p>
      </div>
      <div class="mt-2 sm:mt-0 text-xs text-slate-400">
        Período atual: <?= Html::cleanInputText(date("d/m/Y", strtotime($date_from))) ?>
        &nbsp;até&nbsp;
        <?= Html::cleanInputText(date("d/m/Y", strtotime($date_to))) ?>
      </div>
    </header>

    <!-- Cards de estatísticas -->
    <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
      <!-- Total de Horas -->
      <div class="bg-brandDark text-white rounded-2xl p-5 shadow-md shadow-slate-900/10 
                  hover:shadow-xl hover:-translate-y-1 transition duration-200">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-xs uppercase tracking-wide text-slate-200/70">
              Total de horas no período
            </p>
            <p class="mt-2 text-2xl font-bold">
              <?= $formatted_total_hours ?>
            </p>
          </div>
          <div class="h-10 w-10 rounded-2xl bg-white/10 flex items-center justify-center">
            <span class="text-lg">🧮</span>
          </div>
        </div>
      </div>

      <!-- Média Diária (Dias Úteis) -->
      <div class="bg-brandAccent text-white rounded-2xl p-5 shadow-md shadow-brandAccent/30 
                  hover:shadow-xl hover:-translate-y-1 transition duration-200">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-xs uppercase tracking-wide text-orange-100/80">
              Média diária (dias úteis)
            </p>
            <p class="mt-2 text-2xl font-bold">
              <?= $formatted_daily_avg ?>
            </p>
          </div>
          <div class="h-10 w-10 rounded-2xl bg-white/10 flex items-center justify-center">
            <span class="text-lg">📆</span>
          </div>
        </div>
      </div>

      <!-- Total de Tickets -->
      <div class="bg-white rounded-2xl p-5 shadow-md shadow-slate-900/5 
                  border border-slate-200 hover:shadow-lg hover:-translate-y-1 
                  transition duration-200">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-xs uppercase tracking-wide text-slate-500">
              Total de tickets
            </p>
            <p class="mt-2 text-2xl font-bold text-brandDark">
              <?= (int)$monthly_stats['total_tickets'] ?>
            </p>
          </div>
          <div class="h-10 w-10 rounded-2xl bg-slate-100 flex items-center justify-center">
            <span class="text-lg text-brandDark">🎫</span>
          </div>
        </div>
      </div>

      <!-- Total de Tarefas -->
      <div class="bg-white rounded-2xl p-5 shadow-md shadow-slate-900/5
                  border border-brandAccent/50 hover:shadow-lg hover:-translate-y-1 
                  transition duration-200">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-xs uppercase tracking-wide text-brandAccent">
              Total de tarefas
            </p>
            <p class="mt-2 text-2xl font-bold text-brandAccent">
              <?= (int)$monthly_stats['total_tarefas'] ?>
            </p>
          </div>
          <div class="h-10 w-10 rounded-2xl bg-brandAccent/10 flex items-center justify-center">
            <span class="text-lg text-brandAccent">✅</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Filtros -->
    <section class="bg-white rounded-2xl shadow-md shadow-slate-900/5 border border-slate-200 p-4 sm:p-5">
      <form method="post" class="flex flex-col gap-4 md:flex-row md:items-end md:flex-wrap">
        <input type="hidden" name="_glpi_csrf_token" value="<?= Session::getNewCSRFToken() ?>">

        <?php if ($is_super_admin): ?>
          <div class="flex flex-col">
            <label for="technician_id" class="text-xs font-medium text-slate-600 mb-1">
              Técnico
            </label>
            <select
              name="technician_id"
              id="technician_id"
              class="rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm 
                     text-slate-700 focus:outline-none focus:ring-2 focus:ring-brandAccent/60 
                     focus:border-brandAccent transition">
              <option value="0">-- Selecione --</option>
              <?php foreach ($technicians as $technician): ?>
                <option value="<?= $technician['id'] ?>" <?= ($user_id_to_query == $technician['id']) ? 'selected' : '' ?>>
                  <?= Html::clean($technician['fullname']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="flex flex-col">
          <label class="text-xs font-medium text-slate-600 mb-1">
            De
          </label>
          <input
            type="date"
            name="date_from"
            value="<?= Html::cleanInputText($date_from) ?>"
            class="rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm 
                   text-slate-700 focus:outline-none focus:ring-2 focus:ring-brandAccent/60 
                   focus:border-brandAccent transition">
        </div>

        <div class="flex flex-col">
          <label class="text-xs font-medium text-slate-600 mb-1">
            Até
          </label>
          <input
            type="date"
            name="date_to"
            value="<?= Html::cleanInputText($date_to) ?>"
            class="rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm 
                   text-slate-700 focus:outline-none focus:ring-2 focus:ring-brandAccent/60 
                   focus:border-brandAccent transition">
        </div>

        <div class="flex flex-col md:ml-auto">
          <button
            type="submit"
            class="inline-flex items-center justify-center rounded-xl bg-brandAccent px-4 py-2.5 
                   text-sm font-semibold text-white shadow-md shadow-brandAccent/40 
                   hover:bg-brandAccent/90 hover:-translate-y-[1px] active:translate-y-0 
                   focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-brandAccent 
                   transition">
            🔍 Filtrar
          </button>
        </div>
      </form>
    </section>

    <!-- Tabela de horas -->
    <section class="bg-white rounded-2xl shadow-md shadow-slate-900/5 border border-slate-200 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-800">
          Lançamentos de horas por ticket
        </h2>
        <span class="text-xs text-slate-400">
          <?= count($hours) ?> registro(s)
        </span>
      </div>

      <?php if (!empty($hours)) : ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm text-left text-slate-700">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Data</th>
                <th class="px-4 py-3">Ticket</th>
                <th class="px-4 py-3 text-right">Tempo total (HH:MM)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($hours as $line): ?>
                <?php
                  $sec       = intval($line['total_time']); // segundos
                  $formatted = formatHours($sec);
                ?>
                <tr class="odd:bg-white even:bg-slate-50 hover:bg-slate-100/80 transition-colors">
                  <td class="px-4 py-3 align-top text-slate-600">
                    <?= Html::clean($line['ticket_id']) ?>
                  </td>
                  <td class="px-4 py-3 align-top text-slate-600">
                    <?= date("d/m/Y", strtotime($line['task_date'])) ?>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <a
                      href="<?= $CFG_GLPI['url_base'] ?>/front/ticket.form.php?id=<?= $line['ticket_id'] ?>"
                      class="text-brandDark hover:text-brandAccent font-medium underline-offset-2 hover:underline">
                      <?= Html::clean($line['ticket_name']) ?>
                    </a>
                  </td>
                  <td class="px-4 py-3 align-top text-right font-semibold text-slate-800">
                    <?= $formatted ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="px-4 py-10 flex flex-col items-center justify-center text-center">
          <div class="mb-3 text-3xl">🕒</div>
          <p class="text-sm font-medium text-slate-700">
            Nenhum registro encontrado para o período selecionado.
          </p>
          <p class="text-xs text-slate-400 mt-1">
            Ajuste os filtros acima para ver os lançamentos de horas.
          </p>
        </div>
      <?php endif; ?>
    </section>

  </div>
</div>

<?php
Html::footer();
?>
