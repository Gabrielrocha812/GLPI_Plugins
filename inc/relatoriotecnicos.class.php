<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

// MUDAR AQUI: PluginDashboardDashboard -> PluginRelatoriotecnicosRelatoriotecnicos
class PluginRelatoriotecnicosRelatoriotecnicos extends CommonGLPI {

    static function getTypeName($nb = 0) {
        return __('Dashboard de Horas', 'relatoriotecnicos'); // MUDAR DOMÍNIO DE TRADUÇÃO
    }

    static function canView() {
        return Session::haveRight('plugin_relatoriotecnicos', READ); // MUDAR AQUI
    }

    static function getMenuName() {
        return __('Dashboard de Horas', 'relatoriotecnicos'); // MUDAR DOMÍNIO DE TRADUÇÃO
    }

    static function getMenuContent() {
        return [
            'title' => self::getMenuName(),
            // MUDAR O CAMINHO AQUI
            'page'  => '/plugins/relatoriotecnicos/front/relatoriotecnicos.php'
        ];
    }

    static function getUserHours($user_id, $date_from = null, $date_to = null) {
        global $DB;

        $user_id = intval($user_id);

        // Construção segura da query usando COALESCE para fallback
        $query = "
            SELECT
                t.id AS ticket_id, -- <<< ADICIONE ESTA LINHA
                SUM(tt.actiontime) AS total_time,
                DATE(COALESCE(tt.begin, tt.date)) AS task_date,
                t.name AS ticket_name
            FROM glpi_tickettasks tt
            INNER JOIN glpi_tickets t ON tt.tickets_id = t.id
            WHERE tt.users_id = $user_id
        ";

        $conditions = [];

        // Tratamento correto das datas usando COALESCE
        if (!empty($date_from)) {
            $date_from_escaped = $DB->escape($date_from);
            $conditions[] = "COALESCE(tt.begin, tt.date) >= '$date_from_escaped 00:00:00'";
        }

        if (!empty($date_to)) {
            $date_to_escaped = $DB->escape($date_to);
            $conditions[] = "COALESCE(tt.begin, tt.date) <= '$date_to_escaped 23:59:59'";
        }

        // Adiciona condições se existirem
        if (!empty($conditions)) {
            $query .= " AND " . implode(" AND ", $conditions);
        }

        $query .= "
            GROUP BY DATE(COALESCE(tt.begin, tt.date)), t.id, t.name
            ORDER BY COALESCE(tt.begin, tt.date) DESC
        ";

        $result = $DB->query($query);
        $hours = [];

        if ($result && $DB->numrows($result) > 0) {
            while ($data = $DB->fetchAssoc($result)) {
                $hours[] = $data;
            }
        }

        return $hours;
    }

    /**
     * Obtém uma lista de usuários com perfil de técnico.
     * Altere 'Técnico' se o nome do seu perfil for diferente.
     */
static function getTechnicians() {
    global $DB;

    $ids_permitidos = [9, 4, 7, 10]; // IDs que você quer liberar
    $ids_str = implode(",", $ids_permitidos);

    $query = "
        SELECT u.id, 
               CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.realname,'')) AS fullname
        FROM glpi_users u
        INNER JOIN glpi_profiles_users pu ON u.id = pu.users_id
        WHERE pu.profiles_id = 6
          AND u.is_active = 1
          AND u.id IN ($ids_str)
        ORDER BY fullname ASC
    ";

    $result = $DB->query($query);
    $users = [];

    while ($row = $DB->fetch_assoc($result)) {
        $users[] = $row;
    }

    return $users;
}




    /**
     * Obtém estatísticas mensais
     */
static function getMonthlyStats($user_id, $date_from = null, $date_to = null) {
    global $DB;

    $user_id = intval($user_id);

    // Se não forem passadas datas, usa o mês atual
    if (empty($date_from)) {
        $date_from = date('Y-m-01');
    }
    if (empty($date_to)) {
        $date_to = date('Y-m-t');
    }

    $query = "
        SELECT
            SUM(tt.actiontime) as total_seconds,
            COUNT(DISTINCT tt.tickets_id) as total_tickets,
            COUNT(tt.id) as total_tarefas,
            COUNT(DISTINCT DATE(COALESCE(tt.begin, tt.date))) as total_dias
        FROM glpi_tickettasks tt
        WHERE tt.users_id = $user_id
        AND COALESCE(tt.begin, tt.date) >= '" . $DB->escape($date_from) . " 00:00:00'
        AND COALESCE(tt.begin, tt.date) <= '" . $DB->escape($date_to) . " 23:59:59'
    ";

    $result = $DB->query($query);
    $data = $DB->fetchAssoc($result);

    $total_hours = $data['total_seconds'] / 3600;
    $total_dias = max($data['total_dias'], 1); // Evita divisão por zero
    $daily_average = $total_hours / $total_dias;

    return [
        'total_hours' => round($total_hours, 2),
        'daily_average' => round($daily_average, 2),
        'total_tickets' => $data['total_tickets'],
        'total_tarefas' => $data['total_tarefas'],
        'total_days' => $data['total_dias']
    ];
}

    /**
     * Obtém horas por dia para gráfico
     */
    static function getHoursByDay($user_id, $date_from = null, $date_to = null) {
        global $DB;

        $user_id = intval($user_id);

        if (empty($date_from)) {
            $date_from = date('Y-m-01');
        }
        if (empty($date_to)) {
            $date_to = date('Y-m-t');
        }

        $query = "
            SELECT
                DATE(COALESCE(tt.begin, tt.date)) as dia,
                SUM(tt.actiontime) as total_segundos
            FROM glpi_tickettasks tt
            WHERE tt.users_id = $user_id
            AND COALESCE(tt.begin, tt.date) >= '" . $DB->escape($date_from) . " 00:00:00'
            AND COALESCE(tt.begin, tt.date) <= '" . $DB->escape($date_to) . " 23:59:59'
            GROUP BY DATE(COALESCE(tt.begin, tt.date))
            ORDER BY dia
        ";

        $result = $DB->query($query);
        $hours_by_day = [];

        if ($result && $DB->numrows($result) > 0) {
            while ($data = $DB->fetchAssoc($result)) {
                $hours_by_day[$data['dia']] = round($data['total_segundos'] / 3600, 2);
            }
        }

        return $hours_by_day;
    }
}