<?php
/**
 * Ticket fiscal - 80mm con margen izquierdo 10mm, contenido 60mm
 * Soporta Factura A (desglose IVA) y Factura B/C (IVA contenido - Ley 27.743)
 */
$issuer = $issuer ?? [];
$receiver = $receiver ?? [];
$comprobante = $comprobante ?? [];
$items = $items ?? [];
$subtotal = $subtotal ?? 0;
$iva_total = $iva_total ?? 0;
$total = $total ?? 0;
$cae = $cae ?? '';
$cae_vencimiento = $cae_vencimiento ?? '';
$qr_src = $qr_src ?? '';
$tipo_letra = $tipo_letra ?? 'B';
$tipo_codigo = $tipo_codigo ?? 6;
$condicion_venta = $condicion_venta ?? 'Efectivo';
// Datos específicos por tipo de factura
$es_factura_a = $es_factura_a ?? false;
$es_factura_b = $es_factura_b ?? true;
$es_factura_c = $es_factura_c ?? false;
$es_factura_a_monotributista = $es_factura_a_monotributista ?? false;
$iva_contenido = $iva_contenido ?? 0;
$importe_neto_gravado = $importe_neto_gravado ?? $subtotal;
$iva_desglose = $iva_desglose ?? [];
?>
<!DOCTYPE html>
<html lang="es">

<head>
	<meta charset="UTF-8">
	<title>Ticket</title>
	<style>
		@page {
			margin: 0;
			padding: 0;
			size: 80mm auto;
		}

		html,
		body {
			font-family: 'DejaVu Sans', sans-serif;
			font-size: 9px;
			margin: 0;
			padding: 0;
			color: #000;
		}

		.ticket-wrapper {
			margin: 0;
			padding: 15mm 5mm 3mm 10mm;
			width: 65mm;
			max-width: 65mm;
		}

		table {
			width: 100%;
			border-collapse: collapse;
		}

		td {
			padding: 1px 0;
			vertical-align: top;
			white-space: normal;
			word-wrap: break-word;
			overflow: visible;
		}

		.center {
			text-align: center;
		}

		.right {
			text-align: right;
		}

		.left {
			text-align: left;
		}

		.bold {
			font-weight: bold;
		}

		.mono {
			font-family: 'DejaVu Sans Mono', monospace;
			font-size: 9px;
		}

		.header-title {
			font-size: 11px;
			font-weight: bold;
			text-transform: uppercase;
			text-align: center;
		}

		.header-info {
			font-size: 8px;
			text-align: center;
		}

		.divider {
			border-bottom: 1px dashed #000;
			margin: 3px 0;
			width: 100%;
			display: block;
		}

		.prod-desc {
			font-size: 9px;
			font-weight: bold;
			padding-top: 3px;
			display: block;
			width: 100%;
		}

		.total-row {
			font-size: 12px;
			font-weight: bold;
			padding-top: 5px;
		}

		.factura-tipo {
			font-size: 14px;
			font-weight: bold;
			text-align: center;
			margin: 5px 0;
		}

		.factura-codigo {
			font-size: 8px;
			text-align: center;
		}

		.info-fiscal {
			font-size: 8px;
		}

		.iva-info {
			font-size: 8px;
			margin-top: 3px;
		}
	</style>
</head>

<body>
	<div class="ticket-wrapper">
		<!-- ENCABEZADO EMISOR -->
		<div class="header-title"><?= htmlspecialchars($issuer['razon_social'] ?? '') ?></div>
		<div class="header-info">
			<?= htmlspecialchars($issuer['domicilio'] ?? '') ?>
		</div>
		<div class="header-info">
			CUIT: <?= htmlspecialchars($issuer['cuit'] ?? '') ?>
		</div>
		<div class="header-info">
			<?php
			$condicion_iva = $issuer['condicion_iva'] ?? '';
			$condicion_iva = (strtoupper(trim($condicion_iva)) === 'RESPONSABLE INSCRIPTO')
				? 'IVA Responsable Inscripto'
				: ($condicion_iva ?: 'IVA Responsable Inscripto');
			?>
			<?= htmlspecialchars($condicion_iva) ?>
		</div>
		<?php if (!empty($issuer['iibb'])): ?>
			<div class="header-info">IIBB: <?= htmlspecialchars($issuer['iibb']) ?></div>
		<?php endif; ?>
		<?php if (!empty($issuer['inicio_actividad'])): ?>
			<div class="header-info">Inicio Act.: <?= htmlspecialchars($issuer['inicio_actividad']) ?></div>
		<?php endif; ?>

		<div class="divider"></div>

		<!-- TIPO DE COMPROBANTE -->
		<div class="factura-tipo">FACTURA <?= htmlspecialchars($tipo_letra) ?></div>
		<div class="factura-codigo">Código <?= str_pad((string) (int) $tipo_codigo, 3, '0', STR_PAD_LEFT) ?></div>

		<table style="font-size: 9px; margin-top: 3px;">
			<tr>
				<td class="left">P.V: <?= htmlspecialchars($comprobante['pto_vta'] ?? '') ?></td>
				<td class="right">Nro: <?= htmlspecialchars($comprobante['nro'] ?? '') ?></td>
			</tr>
			<tr>
				<td class="left" colspan="2">Fecha: <?= htmlspecialchars($comprobante['fecha'] ?? '') ?></td>
			</tr>
		</table>

		<div class="divider"></div>

		<!-- RECEPTOR -->
		<div class="info-fiscal">
			<?= htmlspecialchars(strtoupper($receiver['condicion_iva'] ?? 'CONSUMIDOR FINAL')) ?>
			<?php if (!empty($receiver['nombre']) && $receiver['nombre'] !== 'Consumidor Final'): ?>
				<br><?= htmlspecialchars($receiver['nombre']) ?>
			<?php endif; ?>
			<?php if (!empty($receiver['nro_doc']) && $receiver['nro_doc'] !== '0'):
				$isCF = stripos($receiver['condicion_iva'] ?? '', 'Consumidor') !== false;
				$docLabel = $isCF ? 'Doc' : 'CUIL/CUIT';
			?>
				<br><?= $docLabel ?>: <?= htmlspecialchars($receiver['nro_doc']) ?>
			<?php endif; ?>
		</div>

		<div class="divider"></div>

		<!-- ENCABEZADO ITEMS -->
		<?php if ($es_factura_a): ?>
			<table style="font-size: 8px; font-weight: bold; margin-bottom: 2px;">
				<tr>
					<td style="width: 12%;" class="left">CANT</td>
					<td style="width: 33%;" class="left">P.UNIT</td>
					<td style="width: 15%;" class="center">IVA</td>
					<td style="width: 40%;" class="right">IMPORTE</td>
				</tr>
			</table>
		<?php else: ?>
			<table style="font-size: 8px; font-weight: bold; margin-bottom: 2px;">
				<tr>
					<td style="width: 15%;" class="left">CANT</td>
					<td style="width: 40%;" class="left">P.UNIT</td>
					<td style="width: 45%;" class="right">IMPORTE</td>
				</tr>
			</table>
		<?php endif; ?>

		<!-- ITEMS -->
		<?php foreach ($items as $item):
			$cant = (float) ($item['cantidad_calc'] ?? $item['quantity'] ?? $item['cantidad'] ?? 1);
			$desc = htmlspecialchars($item['description'] ?? $item['descripcion'] ?? '');
			$alicuota = (float) ($item['alicuota_iva'] ?? $item['taxRate'] ?? $item['iva_pct'] ?? 21);
			if ($es_factura_a) {
				$precio = (float) ($item['precio_unitario_calc'] ?? $item['unitPrice'] ?? 0);
				$subtotalItem = (float) ($item['subtotal_calc'] ?? ($precio * $cant));
			} else {
				$precio = (float) ($item['precio_unitario_calc'] ?? $item['unitPrice'] ?? $item['subtotal'] ?? 0);
				$subtotalItem = (float) ($item['subtotal_calc'] ?? ($precio * $cant));
			}
			?>
			<div class="prod-desc"><?= $desc ?></div>
			<?php if ($es_factura_a): ?>
				<table class="mono">
					<tr>
						<td style="width: 12%;" class="left"><?= number_format($cant, 0) ?></td>
						<td style="width: 33%;" class="left">$<?= number_format($precio, 2, ',', '.') ?></td>
						<td style="width: 15%;" class="center"><?= number_format($alicuota, 0) ?>%</td>
						<td style="width: 40%;" class="right bold">$<?= number_format($subtotalItem, 2, ',', '.') ?></td>
					</tr>
				</table>
			<?php else: ?>
				<table class="mono">
					<tr>
						<td style="width: 15%;" class="left"><?= number_format($cant, 0) ?></td>
						<td style="width: 40%;" class="left">$<?= number_format($precio, 2, ',', '.') ?></td>
						<td style="width: 45%;" class="right bold">$<?= number_format($subtotalItem, 2, ',', '.') ?></td>
					</tr>
				</table>
			<?php endif; ?>
		<?php endforeach; ?>

		<div class="divider"></div>

		<!-- TOTALES -->
		<?php if ($es_factura_a): ?>
			<!-- FACTURA A: Desglose de IVA -->
			<table style="font-size: 10px;">
				<tr>
					<td class="right">Neto Gravado:</td>
					<td class="right mono">$<?= number_format((float) $importe_neto_gravado, 2, ',', '.') ?></td>
				</tr>
				<?php
				$alicuotasAfip = ['27.0' => '27%', '21.0' => '21%', '10.5' => '10,5%', '5.0' => '5%', '2.5' => '2,5%', '0.0' => '0%'];
				foreach ($alicuotasAfip as $key => $label):
					$valor = $iva_desglose[$key] ?? 0;
					if ($valor > 0):
						?>
						<tr>
							<td class="right">IVA <?= $label ?>:</td>
							<td class="right mono">$<?= number_format((float) $valor, 2, ',', '.') ?></td>
						</tr>
					<?php endif; endforeach; ?>
				<tr class="total-row">
					<td class="right">TOTAL:</td>
					<td class="right mono">$<?= number_format((float) $total, 2, ',', '.') ?></td>
				</tr>
			</table>
		<?php else: ?>
			<!-- FACTURA B/C: Total + IVA Contenido -->
			<table style="font-size: 10px;">
				<tr class="total-row">
					<td class="right">TOTAL:</td>
					<td class="right mono">$<?= number_format((float) $total, 2, ',', '.') ?></td>
				</tr>
			</table>
			<div class="iva-info">
				Ley 27.743 - IVA Contenido: $<?= number_format((float) $iva_contenido, 2, ',', '.') ?>
			</div>
		<?php endif; ?>

		<div class="divider"></div>

		<?php if ($es_factura_a_monotributista): ?>
		<div style="border: 1px solid #c00; padding: 4px; margin-bottom: 5px; background-color: #fff;">
			<p style="margin: 0; font-size: 6px; color: #c00; text-align: center; font-weight: bold; line-height: 1.3;">
				EL CRÉDITO FISCAL DISCRIMINADO EN EL PRESENTE COMPROBANTE SOLO PODRÁ SER COMPUTADO A EFECTOS DEL PROCEDIMIENTO PERMANENTE DE TRANSICIÓN AL RÉGIMEN GENERAL - CAPÍTULO IV DE LA LEY N.° 27.618.
			</p>
		</div>
		<?php endif; ?>

		<!-- CAE -->
		<table style="font-size: 9px;">
			<tr>
				<td class="left bold">CAE:</td>
				<td class="right mono"><?= htmlspecialchars($cae) ?></td>
			</tr>
			<tr>
				<td class="left bold">Vto CAE:</td>
				<td class="right mono"><?= htmlspecialchars($cae_vencimiento) ?></td>
			</tr>
		</table>

		<!-- QR -->
		<?php if ($qr_src !== ''): ?>
			<div class="center" style="margin-top: 5px;">
				<img src="<?= htmlspecialchars($qr_src) ?>" alt="QR AFIP" style="width: 70%; max-width: 45mm;" />
			</div>
		<?php endif; ?>

	</div>
</body>

</html>