<?php
/**
 * Gerador de PDF para análises numerológicas
 */

// Verificar se a biblioteca TCPDF está disponível
if (!file_exists(__DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php')) {
    // Se a biblioteca não estiver disponível, criar diretório vendor
    if (!is_dir(__DIR__ . '/../../vendor/tecnickcom/tcpdf')) {
        mkdir(__DIR__ . '/../../vendor/tecnickcom/tcpdf', 0755, true);
    }

    // Copiar uma versão básica de TCPDF para o diretório vendor
    file_put_contents(__DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php', "<?php
    class TCPDF {
        public function __construct() {
            die('A biblioteca TCPDF não está instalada. Por favor, instale via Composer: composer require tecnickcom/tcpdf');
        }
    }");
}

// Incluir a biblioteca TCPDF
require_once __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';

/**
 * Classe personalizada de PDF para análises numerológicas
 */
class NumerologyPDF extends TCPDF {
    /**
     * Cabeçalho do PDF
     */
    public function Header() {
        // Definir cor de fundo
        $this->SetFillColor(88, 44, 131);

        // Retângulo de cabeçalho
        $this->Rect(0, 0, $this->getPageWidth(), 25, 'F');

        // Definir fonte
        $this->SetFont('helvetica', 'B', 18);
        $this->SetTextColor(255, 255, 255);

        // Título
        $this->SetXY(15, 8);
        $this->Cell(0, 10, 'ANÁLISE NUMEROLÓGICA PERSONALIZADA', 0, false, 'C', 0);
    }

    /**
     * Rodapé do PDF
     */
    public function Footer() {
        // Posição a 15mm do final
        $this->SetY(-15);

        // Definir fonte
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);

        // Número da página
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C');

        // Data de geração
        $this->SetY(-10);
        $this->Cell(0, 10, 'Gerado em ' . date('d/m/Y H:i:s'), 0, false, 'C');
    }

    /**
     * Adicionar título de seção
     *
     * @param string $title Título da seção
     * @param array $color Cor RGB do título
     */
    public function AddSectionTitle($title, $color = array(88, 44, 131)) {
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor($color[0], $color[1], $color[2]);
        $this->Cell(0, 10, $title, 0, true, 'L');
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('helvetica', '', 11);
    }

    /**
     * Adicionar texto com formatação
     *
     * @param string $text Texto a ser adicionado
     */
    public function AddText($text) {
        $this->SetFont('helvetica', '', 11);
        $this->MultiCell(0, 6, $text, 0, 'J');
        $this->Ln(5);
    }

    /**
     * Adicionar número numerológico com descrição
     *
     * @param string $number Número
     * @param string $title Título
     * @param string $description Descrição
     * @param array $color Cor RGB
     */
    public function AddNumberBox($number, $title, $description, $color = array(88, 44, 131)) {
        // Configurações
        $this->SetFont('helvetica', 'B', 14);
        $this->SetTextColor($color[0], $color[1], $color[2]);

        // Círculo com número
        $this->SetLineWidth(0.5);
        $this->SetDrawColor($color[0], $color[1], $color[2]);
        $this->SetFillColor($color[0], $color[1], $color[2]);

        // Posição atual
        $x = $this->GetX();
        $y = $this->GetY();

        // Desenhar círculo
        $radius = 10;
        $this->Circle($x + $radius, $y + $radius, $radius, 0, 360, 'F');

        // Número no círculo
        $this->SetTextColor(255, 255, 255);
        $this->SetXY($x, $y + 5);
        $this->Cell(20, 10, $number, 0, 0, 'C');

        // Título
        $this->SetTextColor($color[0], $color[1], $color[2]);
        $this->SetXY($x + 25, $y);
        $this->Cell(0, 10, $title, 0, 1, 'L');

        // Descrição
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('helvetica', '', 11);
        $this->SetX($x);
        $this->MultiCell(0, 6, $description, 0, 'J');
        $this->Ln(5);
    }

    /**
     * Adicionar ritual diário
     *
     * @param string $ritual Texto do ritual
     */
    public function AddRitual($ritual) {
        // Configurações
        $this->SetLineWidth(0.5);
        $this->SetDrawColor(88, 44, 131);
        $this->SetFillColor(245, 240, 255);

        // Posição atual
        $x = $this->GetX();
        $y = $this->GetY();

        // Desenhar retângulo arredondado
        $this->RoundedRect($x, $y, 180, 70, 5, '1111', 'DF');

        // Título
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(88, 44, 131);
        $this->SetXY($x + 5, $y + 5);
        $this->Cell(170, 10, 'SEU RITUAL DIÁRIO PERSONALIZADO', 0, 1, 'C');

        // Texto do ritual
        $this->SetFont('helvetica', '', 11);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($x + 10, $y + 15);
        $this->MultiCell(160, 6, $ritual, 0, 'J');

        // Avançar cursor
        $this->SetY($y + 75);
    }
}

/**
 * Gerar PDF com análise numerológica
 *
 * @param array $data Dados para o PDF
 * @return string Caminho do arquivo PDF gerado
 */
function generateNumerologyPDF($data) {
    // Extrair dados
    $fullName = $data['formData']['fullName'];
    $birthDate = date('d/m/Y', strtotime($data['formData']['birthDate']));
    $results = $data['results'];

    // Criar diretório para PDFs
    $pdfDir = __DIR__ . '/../../pdfs';
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0755, true);
    }

    // Nome do arquivo
    $filename = 'analise_' . preg_replace('/[^a-zA-Z0-9]/', '_', $fullName) . '_' . date('YmdHis') . '.pdf';
    $filepath = $pdfDir . '/' . $filename;

    // Criar PDF
    $pdf = new NumerologyPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Configurar PDF
    $pdf->SetCreator('Numerologia Cósmica');
    $pdf->SetAuthor('Numerologia Cósmica');
    $pdf->SetTitle('Análise Numerológica para ' . $fullName);
    $pdf->SetSubject('Análise Numerológica');
    $pdf->SetKeywords('numerologia, análise, caminho de vida, destino');

    // Adicionar informações de margem
    $pdf->SetMargins(15, 30, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

    // Desativar quebra de página automática
    $pdf->SetAutoPageBreak(true, 15);

    // Adicionar primeira página
    $pdf->AddPage();

    // Informações pessoais
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(88, 44, 131);
    $pdf->Cell(0, 10, 'Análise Personalizada para:', 0, 1, 'C');

    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, $fullName, 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Data de Nascimento: ' . $birthDate, 0, 1, 'C');
    $pdf->Ln(5);

    // Introdução
    $pdf->AddSectionTitle('INTRODUÇÃO À SUA ANÁLISE NUMEROLÓGICA');
    $pdf->AddText(
        'A Numerologia é uma antiga ciência que estuda a influência que os números exercem em nossas vidas. ' .
        'Cada número possui uma vibração específica que revela aspectos da nossa personalidade, desafios e oportunidades. ' .
        'Esta análise foi criada especialmente para você, baseada em seu nome completo e data de nascimento, ' .
        'revelando padrões energéticos que influenciam sua jornada.');

    // Número do Caminho de Vida
    $pdf->AddNumberBox(
        $results['lifePathNumber'],
        'NÚMERO DO CAMINHO DE VIDA',
        'O Número do Caminho de Vida é derivado da sua data de nascimento e representa seu propósito maior nesta vida, seus desafios e lições centrais. É a energia que você veio desenvolver e expressar nesta encarnação.' . "\n\n" . $results['lifePathMeaning']
    );

    // Talentos e Forças
    $pdf->AddSectionTitle('TALENTOS E FORÇAS NATURAIS');
    $pdf->AddText($results['lifePathTalents']);

    // Número de Destino
    $pdf->AddNumberBox(
        $results['destinyNumber'],
        'NÚMERO DE DESTINO',
        'O Número de Destino é calculado a partir das letras do seu nome completo e revela seus talentos, habilidades e o que você veio realizar nesta vida. Indica o caminho que sua alma escolheu seguir.' . "\n\n" . $results['destinyMeaning'],
        array(132, 70, 175)
    );

    // Ano Pessoal
    $pdf->AddNumberBox(
        $results['personalYearNumber'],
        'ANO PESSOAL',
        'O Ano Pessoal indica a energia dominante no seu ciclo atual, desde seu último aniversário até o próximo. Compreender esta energia ajuda a fluir com as oportunidades e desafios do momento presente.' . "\n\n" . $results['personalYearMeaning'],
        array(194, 81, 128)
    );

    // Nova página para a segunda parte
    $pdf->AddPage();

    // Desafios Atuais
    $pdf->AddSectionTitle('DESAFIOS ATUAIS');
    $pdf->AddText($results['currentChallenges']);

    // Oportunidades Atuais
    $pdf->AddSectionTitle('OPORTUNIDADES ATUAIS');
    $pdf->AddText($results['currentOpportunities']);

    // Ritual Diário
    $pdf->AddSectionTitle('SEU RITUAL DIÁRIO PERSONALIZADO');
    $pdf->AddText('Este ritual foi desenhado especificamente para seu Número do Caminho de Vida. A prática regular deste exercício ajudará você a se alinhar com sua energia natural e propósito de vida, potencializando seu crescimento pessoal e bem-estar.');

    // Adicionar o ritual
    $pdf->AddRitual($results['dailyRitual']);

    // Considerações Finais
    $pdf->AddSectionTitle('CONSIDERAÇÕES FINAIS');
    $pdf->AddText(
        'Lembre-se que você sempre tem livre-arbítrio para escolher como utilizar estas energias numéricas em sua vida. ' .
        'Esta análise oferece orientações e insights sobre potenciais e tendências, mas você é o co-criador do seu caminho. ' .
        'Use este conhecimento como uma ferramenta de autoconhecimento e crescimento pessoal, integrando-o com sabedoria em sua jornada única.');

    // Gerar o PDF
    $pdf->Output($filepath, 'F');

    return $filepath;
}

/**
 * Gerar e enviar PDF diretamente para o navegador
 *
 * @param array $data Dados para o PDF
 * @return void
 */
function outputNumerologyPDF($data) {
    // Extrair dados
    $fullName = $data['formData']['fullName'];
    $birthDate = date('d/m/Y', strtotime($data['formData']['birthDate']));
    $results = $data['results'];

    // Nome do arquivo
    $filename = 'Analise_Numerologica_' . preg_replace('/[^a-zA-Z0-9]/', '_', $fullName) . '.pdf';

    // Criar PDF
    $pdf = new NumerologyPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Configurar PDF
    $pdf->SetCreator('Numerologia Cósmica');
    $pdf->SetAuthor('ComoNumeroAI Inteligência Artifical');
    $pdf->SetTitle('Análise Numerológica para ' . $fullName);
    $pdf->SetSubject('Análise Numerológica');
    $pdf->SetKeywords('numerologia, análise, caminho de vida, destino');

    // Adicionar informações de margem
    $pdf->SetMargins(15, 30, 15);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

    // Desativar quebra de página automática
    $pdf->SetAutoPageBreak(true, 15);

    // Adicionar primeira página
    $pdf->AddPage();

    // Informações pessoais
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(88, 44, 131);
    $pdf->Cell(0, 10, 'Análise Personalizada para:', 0, 1, 'C');

    $pdf->SetFont('helvetica', 'B', 20);
    $pdf->Cell(0, 10, $fullName, 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Data de Nascimento: ' . $birthDate, 0, 1, 'C');
    $pdf->Ln(5);

    // Introdução
    $pdf->AddSectionTitle('INTRODUÇÃO À SUA ANÁLISE NUMEROLÓGICA');
    $pdf->AddText(
        'A Numerologia é uma antiga ciência que estuda a influência que os números exercem em nossas vidas. ' .
        'Cada número possui uma vibração específica que revela aspectos da nossa personalidade, desafios e oportunidades. ' .
        'Esta análise foi criada especialmente para você, baseada em seu nome completo e data de nascimento, ' .
        'revelando padrões energéticos que influenciam sua jornada.');

    // Número do Caminho de Vida
    $pdf->AddNumberBox(
        $results['lifePathNumber'],
        'NÚMERO DO CAMINHO DE VIDA',
        'O Número do Caminho de Vida é derivado da sua data de nascimento e representa seu propósito maior nesta vida, seus desafios e lições centrais. É a energia que você veio desenvolver e expressar nesta encarnação.' . "\n\n" . $results['lifePathMeaning']
    );

    // Talentos e Forças
    $pdf->AddSectionTitle('TALENTOS E FORÇAS NATURAIS');
    $pdf->AddText($results['lifePathTalents']);

    // Número de Destino
    $pdf->AddNumberBox(
        $results['destinyNumber'],
        'NÚMERO DE DESTINO',
        'O Número de Destino é calculado a partir das letras do seu nome completo e revela seus talentos, habilidades e o que você veio realizar nesta vida. Indica o caminho que sua alma escolheu seguir.' . "\n\n" . $results['destinyMeaning'],
        array(132, 70, 175)
    );

    // Ano Pessoal
    $pdf->AddNumberBox(
        $results['personalYearNumber'],
        'ANO PESSOAL',
        'O Ano Pessoal indica a energia dominante no seu ciclo atual, desde seu último aniversário até o próximo. Compreender esta energia ajuda a fluir com as oportunidades e desafios do momento presente.' . "\n\n" . $results['personalYearMeaning'],
        array(194, 81, 128)
    );

    // Nova página para a segunda parte
    $pdf->AddPage();

    // Desafios Atuais
    $pdf->AddSectionTitle('DESAFIOS ATUAIS');
    $pdf->AddText($results['currentChallenges']);

    // Oportunidades Atuais
    $pdf->AddSectionTitle('OPORTUNIDADES ATUAIS');
    $pdf->AddText($results['currentOpportunities']);

    // Ritual Diário
    $pdf->AddSectionTitle('SEU RITUAL DIÁRIO PERSONALIZADO');
    $pdf->AddText('Este ritual foi desenhado especificamente para seu Número do Caminho de Vida. A prática regular deste exercício ajudará você a se alinhar com sua energia natural e propósito de vida, potencializando seu crescimento pessoal e bem-estar.');

    // Adicionar o ritual
    $pdf->AddRitual($results['dailyRitual']);

    // Considerações Finais
    $pdf->AddSectionTitle('CONSIDERAÇÕES FINAIS');
    $pdf->AddText(
        'Lembre-se que você sempre tem livre-arbítrio para escolher como utilizar estas energias numéricas em sua vida. ' .
        'Esta análise oferece orientações e insights sobre potenciais e tendências, mas você é o co-criador do seu caminho. ' .
        'Use este conhecimento como uma ferramenta de autoconhecimento e crescimento pessoal, integrando-o com sabedoria em sua jornada única.');

    // Enviar o PDF para o navegador
    $pdf->Output($filename, 'D');
    exit;
}
