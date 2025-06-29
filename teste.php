<?php
date_default_timezone_set('America/Manaus');

// Define o caminho para o arquivo
$arquivo = 'samebanco.txt';

// Função para somar valores de um JSON
function somarValoresJson($jsonPath) {
    if (!file_exists($jsonPath)) {
        return false;
    }
    $json = file_get_contents($jsonPath);
    $data = json_decode($json, true);
    $soma = 0;
    // Caso o JSON seja um array de objetos ou hashes
    if (is_array($data)) {
        foreach ($data as $item) {
            if (is_array($item) && isset($item['valor']) && is_numeric($item['valor'])) {
                $soma += floatval($item['valor']);
            }
        }
    }
    return $soma;
}

// Inicia a página HTML
echo '<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Prontuários</title>
</head>
<body>';

if (file_exists($arquivo)) {
    // Lê o conteúdo do arquivo
    $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($linhas) {        $dados = [];
        $totalPaginasDigitalizado = 0; // Variável para somar o total de páginas para "DIGITALIZADO"
        $totalPaginasPorColaborador = []; // [colaborador => total]

        // Processa cada linha do arquivo para aplicar as remoções
        foreach ($linhas as $linha) {
            $colunas = explode(',', $linha);

            // Aplica as alterações específicas em cada coluna
            $colunas[0] = preg_replace('/\[.*?\]|Colaborador:|[0-9]{2}\/[0-9]{2}\/[0-9]{4}|[0-9]{2}:[0-9]{2}:[0-9]{2}/', '', $colunas[0]);
            $colunas[1] = str_replace('Processo:', '', $colunas[1]);
            $colunas[2] = str_replace('Quant/Prontuário:', '', $colunas[2]);
            $colunas[3] = str_replace('Quant/Páginas:', '', $colunas[3]);
            $colunas[4] = str_replace('Data:', '', $colunas[4]);
            $colunas[5] = str_replace('Observação:', '', $colunas[5]);

            $colunas = array_map('trim', $colunas);
            $dados[] = $colunas;            // Soma o total de páginas apenas para "DIGITALIZADO"
            if (stripos($colunas[1], "DIGITALIZADO") !== false) {
                $totalPaginasDigitalizado += (int) $colunas[3];
                // Soma por colaborador
                $colaborador = $colunas[0];
                if (!isset($totalPaginasPorColaborador[$colaborador])) {
                    $totalPaginasPorColaborador[$colaborador] = 0;
                }
                $totalPaginasPorColaborador[$colaborador] += (int) $colunas[3];
            }
        }

        // Ordena os dados pela coluna UEP/BOX (índice 5)
        usort($dados, function ($a, $b) {
            return strcmp($a[5], $b[5]);
        });

        // Agrupa os dados por UEP/BOX e calcula os totais
        $grupos = [];
        foreach ($dados as $linha) {
            $uepBox = $linha[5];
            $processo = $linha[1];
            $prontuario = (int) $linha[2];
            $paginas = (int) $linha[3];

            if (!isset($grupos[$uepBox])) {
                $grupos[$uepBox] = [
                    'linhas' => [],
                    'total_prontuario' => 0,
                    'total_paginas' => 0,
                ];
            }

            $grupos[$uepBox]['linhas'][] = $linha;

            // Somar apenas se a coluna Processo conter "DIGITALIZADO"
            if (stripos($processo, "DIGITALIZADO") !== false) {
                $grupos[$uepBox]['total_prontuario'] += $prontuario;
                $grupos[$uepBox]['total_paginas'] += $paginas;            }
        }

        // NOVO: Cálculo dos totais de prontuários por período
        $totalProntuarioGeral = 0;
        $totalProntuarioAno = 0;
        $totalProntuarioMes = 0;
        $totalProntuarioSemana = 0;
        $totalProntuarioDia = 0;
        
        $anoAtual = date('Y');
        $mesAtual = date('m');
        $diaAtual = date('d');
        $hoje = date('d-m-Y'); // Formato para comparação direta
        $inicioSemana = date('Y-m-d', strtotime('monday this week'));
        $fimSemana = date('Y-m-d', strtotime('sunday this week'));

        // Usa os dados já processados
        foreach ($dados as $linha) {
            $processo = trim($linha[1]);
            $prontuario = (int) trim($linha[2]);
            $dataStr = trim($linha[4]); // Quarto campo (Data)
            
            // Só conta se for DIGITALIZADO
            if (stripos($processo, "DIGITALIZADO") !== false && $prontuario > 0) {
                $totalProntuarioGeral += $prontuario;
                
                // Converte data para verificações de período
                if (!empty($dataStr)) {
                    // Tenta diferentes formatos de data
                    $dataConvertida = null;
                    
                    // Formato dd-mm-yyyy
                    if (preg_match('/(\d{1,2})-(\d{1,2})-(\d{4})/', $dataStr, $matches)) {
                        $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                        $mes = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                        $ano = $matches[3];
                        $dataConvertida = "$ano-$mes-$dia";
                    }
                    // Formato dd/mm/yyyy
                    else if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $dataStr, $matches)) {
                        $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                        $mes = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                        $ano = $matches[3];
                        $dataConvertida = "$ano-$mes-$dia";
                    }
                    
                    if ($dataConvertida) {
                        // Ano atual
                        if ($ano == $anoAtual) {
                            $totalProntuarioAno += $prontuario;
                        }
                        
                        // Mês atual
                        if ($ano == $anoAtual && $mes == $mesAtual) {
                            $totalProntuarioMes += $prontuario;
                        }
                        
                        // Dia atual (hoje)
                        $dataFormatoComparacao = "$dia-$mes-$ano";
                        if ($dataFormatoComparacao == $hoje) {
                            $totalProntuarioDia += $prontuario;
                        }
                        
                        // Semana atual
                        if ($dataConvertida >= $inicioSemana && $dataConvertida <= $fimSemana) {
                            $totalProntuarioSemana += $prontuario;
                        }
                    }
                }
            }
        }

        // Função para calcular total de prontuários por ano e mês específicos
        function calcularProntuariosPorPeriodo($linhas, $anoFiltro = null, $mesFiltro = null) {
            $total = 0;
            foreach ($linhas as $linha) {
                $colunas = explode(',', $linha);
                $colunas = array_map('trim', $colunas);
                
                $colunas[1] = str_replace('Processo:', '', $colunas[1]);
                $colunas[2] = str_replace('Quant/Prontuário:', '', $colunas[2]);
                $colunas[4] = str_replace('Data:', '', $colunas[4]);
                
                $processo = trim($colunas[1]);
                $prontuario = (int) trim($colunas[2]);
                $dataStr = trim($colunas[4]);
                
                if (stripos($processo, "DIGITALIZADO") !== false && $prontuario > 0) {
                    if (!empty($dataStr)) {
                        // Tenta diferentes formatos de data
                        $matches = null;
                        if (preg_match('/(\d{1,2})-(\d{1,2})-(\d{4})/', $dataStr, $matches) ||
                            preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $dataStr, $matches)) {
                            
                            $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                            $mes = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                            $ano = $matches[3];
                            
                            $incluir = true;
                            
                            if ($anoFiltro !== null && $ano != $anoFiltro) {
                                $incluir = false;
                            }
                            
                            if ($mesFiltro !== null && $mes != str_pad($mesFiltro, 2, '0', STR_PAD_LEFT)) {
                                $incluir = false;
                            }
                            
                            if ($incluir) {
                                $total += $prontuario;
                            }
                        }
                    }
                }
            }
            return $total;
        }

        // NOVO: Contagem de prontuários de 2013 no intervalo 3240-3740 (apenas DIGITALIZADO)
        $contadorProntuarios2013 = -1;
        foreach ($linhas as $linha) {
            $colunas = explode(',', $linha);
            $colunas = array_map('trim', $colunas);
            
            // Remove prefixos das colunas
            $processo = str_replace('Processo:', '', $colunas[1]);
            $processo = trim($processo);
            
            // Só conta se o processo for DIGITALIZADO
            if (stripos($processo, "DIGITALIZADO") !== false) {
                // Pega o último campo (índice 5 - UEP/BOX/Observação)
                if (isset($colunas[5])) {
                    $ultimoCampo = trim($colunas[5]);
                    // Extrai números do último campo usando regex
                    if (preg_match('/(\d+)/', $ultimoCampo, $matches)) {
                        $numero = (int)$matches[1];
                        // Verifica se está no intervalo de 3240 a 3740 (inclusivo)
                        if ($numero >= 3240 && $numero <= 3740) {
                            $contadorProntuarios2013++;
                        }
                    }
                }
            }
        }

        // CSS para a tabela com cabeçalho fixo e filtro
        echo '<style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                padding: 0;
                background-color: #f4f4f9;
            }
            .filter-box {
                margin-bottom: 10px;
            }
            input[type="text"] {
                width: 100%;
                padding: 10px;
                font-size: 16px;
                margin-bottom: 20px;
                border: 1px solid #ccc;
                border-radius: 5px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .table-wrapper {
                max-height: 500px;
                overflow-y: auto;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                background: #fff;
            }
            table {
                width: 100%;
                min-width: 800px;
                border-collapse: collapse;
                font-size: 16px;
                text-align: center;
                background-color: #fff;
            }
            thead {
                background-color: #007BFF;
                color: #fff;
                position: sticky;
                top: 0;
                z-index: 10;
            }
            th, td {
                padding: 12px 15px;
                border: 1px solid #ddd;
                white-space: nowrap;
            }
            th {
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            tbody tr:nth-child(odd) {
                background-color: #f9f9f9;
            }
            tbody tr:hover {
                background-color: #f1f1f1;
            }
            tbody tr.total {
                font-weight: bold;
                background-color: #007BFF;
                color: #fff;
            }
            .no-results {
                text-align: center;
                font-style: italic;
                color: #777;
            }
            .colaborador-total-container {
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                margin-bottom: 20px;
                padding: 20px;
                max-width: 600px;
                display: inline-block;
                vertical-align: top;
            }
            .chart-container {
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                margin-bottom: 20px;
                padding: 20px;
                max-width: 600px;
                display: inline-block;
                vertical-align: top;
                margin-left: 20px; /* Adicionado para criar um espaçamento horizontal entre os containers */
            }
            .json-total-container {
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 123, 255, 0.08);
                margin-bottom: 28px;
                padding: 0px 23px;
                max-width: 260px;
                display: block;
                margin-top: -10px;
                margin-left: 0;
                font-family: "Segoe UI", Arial, sans-serif;
                font-size: 18px;
                color: #222;
                font-weight: 600;
                border-left: 0px solid #007BFF;
            }
            .json-total-container h3 {
                margin: 20px 0 5px 0;
                font-family: "Segoe UI", Arial, sans-serif;
                font-size: 18px;
                font-weight: 600;
                color: #007BFF;
            }
            .prontuario-total-container {
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                margin-bottom: 20px;
                padding: 20px;
                max-width: 480px;
                display: inline-block;
                vertical-align: top;
                margin-left: 20px;
                font-family: "Segoe UI", Arial, sans-serif;
            }
            .prontuario-total-container h3 {
                margin: 0 0 15px 0;
                font-family: "Segoe UI", Arial, sans-serif;
                font-size: 18px;
                font-weight: 600;
                color: #007BFF;
                border-bottom: 2px solid #f0f0f0;
                padding-bottom: 10px;
            }
            .filter-buttons {
                display: flex;
                gap: 8px;
                margin-bottom: 15px;
                flex-wrap: wrap;
            }
            .filter-btn {
                padding: 8px 12px;
                border: 1px solid #007BFF;
                background: #fff;
                color: #007BFF;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                font-weight: 500;
                transition: all 0.3s ease;
            }
            .filter-btn.active {
                background: #007BFF;
                color: #fff;
            }
            .filter-btn:hover {
                background: #0056b3;
                color: #fff;
            }
            .total-display {
                font-size: 24px;
                font-weight: 700;
                color: #28a745;
                text-align: center;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 6px;
                border-left: 4px solid #28a745;
            }
            .custom-filters {
                margin-bottom: 15px;
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                align-items: center;
            }
            .custom-filter-group {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .custom-filter-group label {
                font-size: 12px;
                font-weight: 500;
                color: #666;
                margin: 0;
            }
            .custom-input {
                padding: 6px 10px;
                border: 1px solid #007BFF;
                border-radius: 4px;
                font-size: 12px;
                width: 80px;
                text-align: center;
            }
            .custom-select {
                padding: 6px 10px;
                border: 1px solid #007BFF;
                border-radius: 4px;
                font-size: 12px;
                background: #fff;
                width: 120px;
            }
            .apply-filter-btn {
                padding: 6px 12px;
                background: #28a745;
                color: #fff;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                font-weight: 500;
                margin-top: 18px;
                transition: all 0.3s ease;
            }
            .apply-filter-btn:hover {
                background: #218838;
            }
        </style>';

        // NOVO CONTAINER: Digitalizadas por colaborador
        echo '<div class="colaborador-total-container">';
        echo '<h3>Digitalizadas por Colaborador</h3>';
        if (!empty($totalPaginasPorColaborador)) {
            echo '<ul class="colaborador-total-list">';
            // Ordena decrescente pelo total de páginas
            arsort($totalPaginasPorColaborador);
            foreach ($totalPaginasPorColaborador as $colaborador => $total) {
                echo '<li style="display: flex; justify-content: space-between; width: 100%;">' .
                    '<span>' . htmlspecialchars($colaborador) . '</span>' .
                    '<span>' . number_format($total, 0, ',', '.') . '</span>' .
                    '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>Nenhum colaborador digitalizou páginas.</p>';
        }
        echo '</div>';

        // NOVO CONTAINER: Gráfico de pizza
        echo '<div class="chart-container">';
        echo '<h3>Digitalizadas por Colaborador</h3>';
        echo '<canvas id="pizzaChart"></canvas>';
        echo '</div>';

        // NOVO CONTAINER: Gráfico de linha por hora (DIGITALIZADO)        // DADOS PARA O GRÁFICO DE LINHA: variação de páginas digitadas por hora (das 7h às 23h)
        $paginasPorHora = array_fill_keys(range(7, 23), 0); // 7h até 23h
        $totalPaginasHoje = 0;
        $hoje = date('d-m-Y'); // Formato para comparação com o campo Data
        
        // Usa os dados originais ($linhas) que contêm o timestamp não processado
        foreach ($linhas as $linha) {
            $colunas = explode(',', $linha);
            
            // Não remove o timestamp do campo 0 para esta análise
            $colaboradorOriginal = trim($colunas[0]); // Campo 0 com timestamp
            $processo = str_replace('Processo:', '', trim($colunas[1])); // Campo 1 - Processo
            $paginas = (int) str_replace('Quant/Páginas:', '', trim($colunas[3])); // Campo 3 - Páginas
            $dataStr = str_replace('Data:', '', trim($colunas[4])); // Campo 4 - Data
            
            // Só processa se for DIGITALIZADO
            if (stripos($processo, "DIGITALIZADO") !== false && $paginas > 0) {
                // Verifica se a data é hoje
                $dataEHoje = false;
                if (!empty($dataStr)) {
                    // Normaliza a data para comparação
                    if (preg_match('/(\d{1,2})-(\d{1,2})-(\d{4})/', $dataStr, $matches) ||
                        preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $dataStr, $matches)) {
                        $dia = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                        $mes = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                        $ano = $matches[3];
                        $dataFormatada = "$dia-$mes-$ano";
                        $dataEHoje = ($dataFormatada == $hoje);
                    }
                }
                
                if ($dataEHoje) {
                    // Extrai hora do campo 0 original (que contém timestamp)
                    if (preg_match('/\[(\d{4}-\d{2}-\d{2}) (\d{2}):(\d{2}):(\d{2})\]/', $colaboradorOriginal, $match)) {
                        $hora = (int)$match[2];
                        // Só considera horas entre 7 e 23
                        if ($hora >= 7 && $hora <= 23) {
                            $paginasPorHora[$hora] += $paginas;
                            $totalPaginasHoje += $paginas;
                        }
                    }
                }
            }
        }
        
        // Debug para verificar se os dados estão sendo coletados
        echo '<!-- Debug Gráfico de Linha: ';
        echo 'Total páginas hoje: ' . $totalPaginasHoje . ', ';
        echo 'Data hoje: ' . $hoje . ', ';
        echo 'Páginas por hora: ' . json_encode($paginasPorHora) . ', ';
        if (!empty($linhas)) {
            echo 'Exemplo linha original: ' . substr($linhas[0], 0, 100) . '... ';
        }
        echo ' -->';
        echo '<div class="chart-container">';
        echo '<h3 style="margin-bottom: 10px; font-family: \"Segoe UI\", Arial, sans-serif; font-size: 18px; font-weight: 600; color: #222;">Variação de Páginas Digitadas Hoje</h3>';
        echo '<div style="font-family: \"Segoe UI\", Arial, sans-serif; font-size: 12px; color: #007BFF; background: #f4f8ff; border-radius: 8px; padding: 8px 14px; margin-bottom: 18px; display: inline-block; box-shadow: 0 2px 8px rgba(0,123,255,0.07); letter-spacing: 0.5px; font-weight: 500;">Total de Páginas: <span style="font-weight:700; color:#0056b3;">' . number_format($totalPaginasHoje, 0, ',', '.') . '</span></div>';
        echo '<canvas id="lineChart"></canvas>';
        echo '</div>';

        // NOVO CONTAINER: Prontuários de 2013 (3240-3740)
        echo '<div class="chart-container">';
        echo '<h3 style="margin-bottom: 10px; font-family: \"Segoe UI\", Arial, sans-serif; font-size: 18px; font-weight: 600; color: #222;">Prontuários de 2013</h3>';
        echo '<div style="font-family: \"Segoe UI\", Arial, sans-serif; font-size: 12px; color: #007BFF; background: #f4f8ff; border-radius: 8px; padding: 8px 14px; margin-bottom: 18px; display: inline-block; box-shadow: 0 2px 8px rgba(0,123,255,0.07); letter-spacing: 0.5px; font-weight: 500;">        </div>';
        echo '<div class="total-display" style="font-size: 48px; font-weight: 700; color: #dc3545; text-align: center; padding: 30px; background: #fff5f5; border-radius: 12px; border-left: 6px solid #dc3545; box-shadow: 0 4px 12px rgba(220,53,69,0.15);">' . number_format($contadorProntuarios2013, 0, ',', '.') . '</div>';
        echo '<div style="text-align: center; margin-top: 15px; font-family: \"Segoe UI\", Arial, sans-serif; font-size: 14px; color: #666; font-weight: 500;">    </div>';
        echo '</div>';

        // NOVO CONTAINER: Total de Prontuários com Filtros (ao lado do gráfico de linha)
        echo '<div class="prontuario-total-container">';
        echo '<h3>Total de Prontuários</h3>';
          // Debug - mostra os valores calculados e alguns dados de exemplo
        echo '<!-- Debug: ';
        echo 'Geral=' . $totalProntuarioGeral . ', ';
        echo 'Ano=' . $totalProntuarioAno . ', ';
        echo 'Mês=' . $totalProntuarioMes . ', ';
        echo 'Semana=' . $totalProntuarioSemana . ', ';
        echo 'Dia=' . $totalProntuarioDia . ', ';
        echo 'Data hoje: ' . $hoje . ', ';
        echo 'Total registros: ' . count($dados) . ', ';
        if (count($dados) > 0) {
            echo 'Exemplo primeiro registro: [' . implode(', ', $dados[0]) . '], ';
            // Debug para mostrar algumas datas do arquivo
            $contadorDebugDias = 0;
            echo 'Datas encontradas: ';
            foreach ($dados as $linha) {
                if (!empty($linha[4]) && $contadorDebugDias < 10) {
                    echo $linha[4] . '; ';
                    $contadorDebugDias++;
                }
            }
        }
        echo ' -->';
        
        // Filtros rápidos
        echo '<div class="filter-buttons">';
        echo '<button class="filter-btn active" onclick="showProntuarioTotal(\'geral\', ' . $totalProntuarioGeral . ')">Geral</button>';
        echo '<button class="filter-btn" onclick="showProntuarioTotal(\'ano\', ' . $totalProntuarioAno . ')">Este Ano</button>';
        echo '<button class="filter-btn" onclick="showProntuarioTotal(\'mes\', ' . $totalProntuarioMes . ')">Este Mês</button>';
        echo '<button class="filter-btn" onclick="showProntuarioTotal(\'semana\', ' . $totalProntuarioSemana . ')">Esta Semana</button>';
        echo '<button class="filter-btn" onclick="showProntuarioTotal(\'dia\', ' . $totalProntuarioDia . ')">Hoje</button>';
        echo '</div>';
        
        // Filtros customizados
        echo '<div class="custom-filters">';
        echo '<div class="custom-filter-group">';
        echo '<label for="anoCustom">Ano:</label>';
        echo '<input type="number" id="anoCustom" class="custom-input" placeholder="' . date('Y') . '" min="2020" max="2030">';
        echo '</div>';
        echo '<div class="custom-filter-group">';
        echo '<label for="mesCustom">Mês:</label>';
        echo '<select id="mesCustom" class="custom-select">';
        echo '<option value="">Todos</option>';
        echo '<option value="1">Janeiro</option>';
        echo '<option value="2">Fevereiro</option>';
        echo '<option value="3">Março</option>';
        echo '<option value="4">Abril</option>';
        echo '<option value="5">Maio</option>';
        echo '<option value="6">Junho</option>';
        echo '<option value="7">Julho</option>';
        echo '<option value="8">Agosto</option>';
        echo '<option value="9">Setembro</option>';
        echo '<option value="10">Outubro</option>';
        echo '<option value="11">Novembro</option>';
        echo '<option value="12">Dezembro</option>';
        echo '</select>';
        echo '</div>';
        echo '<button class="apply-filter-btn" onclick="aplicarFiltroCustomizado()">Aplicar</button>';
        echo '</div>';
        
        echo '<div class="total-display" id="prontuarioDisplay">' . number_format($totalProntuarioGeral, 0, ',', '.') . '</div>';
        echo '</div>';

        // NOVO CONTAINER: Soma total dos valores do JSON (Certificados)
        $jsonPath = __DIR__ . '/certificado/10.json';
        $somaValoresJson = somarValoresJson($jsonPath);
        echo '<div class="json-total-container">';
        echo '<h3>Certificados</h3>';
        if ($somaValoresJson !== false) {
            echo '<span style="color:#000000;">' . number_format($somaValoresJson, 0, ',', '.') . '</span>';
        } else {
            echo '<span style="color:#2b9348;">Arquivo não encontrado ou inválido.</span>';
        }
        echo '</div>';

        // NOVO CONTAINER: Filtro por Data com Dropdown
        echo '<div class="json-total-container" style="max-width: 400px; padding: 20px;">';
        echo '<h3>Quantidade de Paginas ou Prontuários</h3>';
        echo '<div style="margin-bottom: 5px;">';
        echo '<label for="dataFiltroNovo" style="display: block; margin-bottom: 5px; font-size: 14px; color: #666;"> </label>';
        echo '<input type="text" id="dataFiltroNovo" placeholder="dd-mm-yyyy" style="width:30%; padding: 8px; border: 1px solid #007BFF; border-radius: 4px; font-size: 14px;" maxlength="10">';
        echo '</div>';
        echo '<div style="margin-bottom: 15px;">';
        echo '<label for="tipoFiltroDropdown" style="display: block; margin-bottom: 5px; font-size: 14px; color: #666;">Filtrar por:</label>';
        echo '<select id="tipoFiltroDropdown" style="width: 55%; padding: 8px; border: 1px solid #007BFF; border-radius: 4px; font-size: 14px; background: #fff;">';
        echo '<option value=""> </option>';
        echo '<option value="paginas">Quant/Páginas</option>';
        echo '<option value="prontuarios">Quant/Prontuário</option>';
        echo '</select>';
        echo '</div>';
        echo '<button onclick="aplicarFiltroDataNovo()" style="width: 55%; padding: 10px; background: #007BFF; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; margin-bottom: 15px;">Aplicar Filtro</button>';
        echo '<div style="margin-top: 15px;">';
        echo '<label style="display: block; margin-bottom: 5px; font-size: 14px; color: #666;">Resultado:</label>';
        echo '<div id="resultadoFiltroDataNovo" style="padding: 12px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #007BFF; font-size: 16px; font-weight: 600; color: #333; text-align: center;">0</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="total-pages" style="margin-bottom: 20px; font-size: 18px; font-weight: bold; color: #000000;">
       Total de Páginas Digitalizadas: <span style="color: #FF4500;">' . number_format($totalPaginasDigitalizado, 0, ',', '.') . '</span>
      </div>';

        // Caixa de texto de filtro e tabela
        echo '<div class="filter-box">
                <input type="text" id="filterInput" autocomplete="off" placeholder="Digite para filtrar o UEP/BOX...">
              </div>';

        // WRAPPER EM VOLTA DA TABELA!!!
        echo '<div class="table-wrapper">';
        echo '<table id="dataTable">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Colaborador</th>';
        echo '<th>Processo</th>';
        echo '<th>Quant/Prontuário</th>';
        echo '<th>Quant/Páginas</th>';
        echo '<th>Data</th>';
        echo '<th>UEP/BOX</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($grupos as $uepBox => $grupo) {
            foreach ($grupo['linhas'] as $linha) {
                echo '<tr>';
                foreach ($linha as $coluna) {
                    echo '<td>' . htmlspecialchars($coluna) . '</td>';
                }
                echo '</tr>';
            }

            // Adiciona uma linha com os totais para o grupo
            echo '<tr class="total">';
            echo '<td colspan="2">Total</td>';
            echo '<td>' . $grupo['total_prontuario'] . '</td>';
            echo '<td>' . $grupo['total_paginas'] . '</td>';
            echo '<td colspan="2">' . htmlspecialchars($uepBox) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>'; // fecha .table-wrapper        // Script JavaScript para o filtro e gráfico de pizza
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        echo '<script>
// Dados para cálculo dinâmico (passados do PHP)
const dadosProcessados = ' . json_encode($dados) . ';

// Função para calcular prontuários por período customizado
function calcularProntuarioCustomizado(ano, mes) {
    let total = 0;
    let contadorProcessados = 0;
    
    dadosProcessados.forEach((linha, index) => {
        const processo = linha[1] ? linha[1].trim() : "";
        const prontuario = parseInt(linha[2] ? linha[2].trim() : "0") || 0;
        const dataStr = linha[4] ? linha[4].trim() : "";
        
        if (processo.toLowerCase().includes("digitalizado") && prontuario > 0) {
            contadorProcessados++;
            
            // Tenta diferentes formatos de data
            let matchData = dataStr.match(/(\d{1,2})-(\d{1,2})-(\d{4})/) || 
                           dataStr.match(/(\d{1,2})\/(\d{1,2})\/(\d{4})/);
            
            if (matchData) {
                const diaLinha = matchData[1].padStart(2, "0");
                const mesLinha = matchData[2].padStart(2, "0");
                const anoLinha = matchData[3];
                
                let incluir = true;
                
                if (ano && anoLinha !== ano.toString()) {
                    incluir = false;
                }
                
                if (mes && mesLinha !== mes.toString().padStart(2, "0")) {
                    incluir = false;
                }
                
                if (incluir) {
                    total += prontuario;
                }
            }
        }
    });
    
    console.log("Prontuários digitalizados encontrados:", contadorProcessados);
    return total;
}

// Função para aplicar filtro customizado
function aplicarFiltroCustomizado() {
    const ano = document.getElementById("anoCustom").value;
    const mes = document.getElementById("mesCustom").value;
    
    if (!ano && !mes) {
        alert("Por favor, selecione pelo menos um filtro (ano ou mês).");
        return;
    }
    
    const total = calcularProntuarioCustomizado(ano, mes);
    
    // Remove classe active de todos os botões
    document.querySelectorAll(".filter-btn").forEach(btn => btn.classList.remove("active"));
    
    // Atualiza o display
    document.getElementById("prontuarioDisplay").textContent = total.toLocaleString("pt-BR");
    
    // Debug
    console.log("Filtro aplicado - Ano:", ano, "Mês:", mes, "Total:", total);
}

// Função para mostrar o total de prontuários (filtros rápidos)
function showProntuarioTotal(periodo, valor) {
    // Remove classe active de todos os botões
    document.querySelectorAll(".filter-btn").forEach(btn => btn.classList.remove("active"));
    
    // Adiciona classe active ao botão clicado
    if (event && event.target) {
        event.target.classList.add("active");
    }
    
    // Limpa os campos customizados
    document.getElementById("anoCustom").value = "";
    document.getElementById("mesCustom").value = "";
    
    // Atualiza o display com o valor formatado
    document.getElementById("prontuarioDisplay").textContent = valor.toLocaleString("pt-BR");
    
    // Debug
    console.log("Filtro rápido - Período:", periodo, "Valor:", valor);
}

// Debug inicial para verificar os dados
console.log("Dados carregados:", dadosProcessados.length, "registros");
console.log("Primeira linha exemplo:", dadosProcessados[0]);

// Função específica para verificar dados de hoje
function verificarDadosHoje() {
    let totalHoje = 0;
    let datasEncontradas = [];
    const hoje = "26-06-2025"; // Data atual correta
    
    dadosProcessados.forEach(linha => {
        const processo = linha[1] ? linha[1].trim() : "";
        const prontuario = parseInt(linha[2] ? linha[2].trim() : "0") || 0;
        const dataStr = linha[4] ? linha[4].trim() : "";
        
        if (processo.toLowerCase().includes("digitalizado") && prontuario > 0) {
            // Adiciona data à lista para debug
            if (datasEncontradas.length < 10) {
                datasEncontradas.push(dataStr);
            }
            
            // Verifica se é hoje (26-06-2025)
            if (dataStr === hoje || dataStr === "26-6-2025" || 
                dataStr === "26/06/2025" || dataStr === "26/6/2025") {
                totalHoje += prontuario;
            }
        }
    });
    
    console.log("Verificação dados hoje:", {
        totalHoje: totalHoje,
        dataHoje: hoje,
        primeirasDatas: datasEncontradas
    });
    
    return totalHoje;
}

// Executa verificação inicial
verificarDadosHoje();

document.getElementById("filterInput").addEventListener("input", function() {
    var filter = this.value.toLowerCase();
    var rows = document.querySelectorAll("#dataTable tbody tr");
    var groupHasVisible = {};
    var currentGroup = "";

    rows.forEach(function(row) {
        if (row.classList.contains("total")) {
            // Linha de total: determina se deve ser exibida baseada no grupo
            var groupKey = row.cells[row.cells.length - 1].textContent.toLowerCase();
            row.style.display = groupHasVisible[groupKey] ? "" : "none";
        } else {
            // Linhas normais: verifica se atende ao filtro
            var text = row.textContent.toLowerCase();
            var visible = text.includes(filter);
            row.style.display = visible ? "" : "none";

            if (visible) {
                // Identifica o grupo (UEP/BOX) da linha atual
                var groupKey = row.cells[row.cells.length - 1].textContent.toLowerCase();
                groupHasVisible[groupKey] = true;
            }
        }
    });
});

// Dados para o gráfico de pizza
var ctx = document.getElementById("pizzaChart").getContext("2d");
var data = {
    labels: ' . json_encode(array_keys($totalPaginasPorColaborador)) . ',
    datasets: [{
        data: ' . json_encode(array_values($totalPaginasPorColaborador)) . ',
        backgroundColor: ["#FF6384", "#36A2EB", "#FFCE56", "#4BC0C0", "#9966FF", "#FF9F40"],
        hoverBackgroundColor: ["#FF6384", "#36A2EB", "#FFCE56", "#4BC0C0", "#9966FF", "#FF9F40"]
    }]
};
var pizzaChart = new Chart(ctx, {
    type: "pie",
    data: data
});

// Dados para o gráfico de linha
const ctxLine = document.getElementById("lineChart").getContext("2d");
const lineChart = new Chart(ctxLine, {
    type: "line",
    data: {
        labels: ["7h", "8h", "9h", "10h", "11h", "12h", "13h", "14h", "15h", "16h", "17h", "18h", "19h", "20h", "21h", "22h", "23h", "00h"],
        datasets: [{
            label: "Páginas Digitadas",
            data: ' . json_encode(array_values($paginasPorHora)) . ',
            borderColor: "#007BFF",
            backgroundColor: "rgba(0, 123, 255, 0.2)",
            fill: true,
        }],
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: "top",
            },
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: "Hora",
                },
            },
            y: {
                title: {
                    display: true,
                    text: "Quantidade de Páginas",
                },
                beginAtZero: true,
            },
        },
    },
});

// NOVO: Funcionalidade para o filtro por data com dropdown
// Máscara dinâmica para o campo de data
function aplicarMascaraDataNovo() {
    const dataInput = document.getElementById("dataFiltroNovo");
    if (dataInput) {
        dataInput.addEventListener("input", function(e) {
            let value = e.target.value.replace(/\D/g, ""); // Remove caracteres não numéricos
            
            // Aplica a máscara dd-mm-yyyy
            if (value.length >= 2) {
                value = value.substring(0,2) + "-" + value.substring(2);
            }
            if (value.length >= 5) {
                value = value.substring(0,5) + "-" + value.substring(5,9);
            }
            
            e.target.value = value;
        });
    }
}

// Função para aplicar o filtro por data com dropdown
function aplicarFiltroDataNovo() {
    const dataInput = document.getElementById("dataFiltroNovo").value;
    const tipoFiltro = document.getElementById("tipoFiltroDropdown").value;
    const resultadoDiv = document.getElementById("resultadoFiltroDataNovo");
    
    // Validações
    if (!dataInput || dataInput.length !== 10) {
        alert("Por favor, digite uma data válida no formato dd-mm-yyyy");
        return;
    }
    
    if (!tipoFiltro) {
        alert("Por favor, selecione um tipo de filtro");
        return;
    }
    
    // Determinar qual coluna filtrar
    const colunaFiltro = tipoFiltro === "prontuarios" ? 2 : 3;
    const nomeColuna = tipoFiltro === "prontuarios" ? "Prontuário" : "Páginas";
    
    let soma = 0;
    let registrosEncontrados = 0;
    
    // Processar os dados
    dadosProcessados.forEach(linha => {
        const processo = linha[1] ? linha[1].trim() : "";
        const dataLinha = linha[4] ? linha[4].trim() : "";
        const valor = parseInt(linha[colunaFiltro] ? linha[colunaFiltro].toString().trim() : "0") || 0;
        
        // Só processar registros DIGITALIZADO
        if (processo.toLowerCase().includes("digitalizado") && valor > 0) {
            // Normalizar formato de data para comparação
            let dataComparacao = "";
            const matchData = dataLinha.match(/(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/);
            if (matchData) {
                const dia = matchData[1].padStart(2, "0");
                const mes = matchData[2].padStart(2, "0");
                const ano = matchData[3];
                dataComparacao = dia + "-" + mes + "-" + ano;
            }
            
            // Verificar se a data coincide
            if (dataComparacao === dataInput) {
                soma += valor;
                registrosEncontrados++;
            }
        }
    });
    
    // Exibir resultado
    resultadoDiv.textContent = soma.toLocaleString("pt-BR");
    resultadoDiv.style.color = soma > 0 ? "#28a745" : "#dc3545";
    
    // Debug
    console.log("Filtro por data (novo) aplicado:", {
        data: dataInput,
        tipo: nomeColuna,
        registrosEncontrados: registrosEncontrados,
        soma: soma
    });
}

// Inicializar funcionalidade do novo filtro quando a página carregar
window.addEventListener("load", function() {
    aplicarMascaraDataNovo();
});

</script>';
    } else {
        echo '<p>O arquivo está vazio.</p>';
    }
} else {
    echo '<p>O arquivo "samebanco.txt" não foi encontrado.</p>';
}

echo '</body></html>';
?>