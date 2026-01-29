<?php
/**
 * Factura A4 - medidas hoja A4 (210×297mm), márgenes 20mm. Diseño referencia Empresa imaginaria S.A.
 * Variables: $issuer, $receiver, $comprobante, $items, $subtotal, $iva_total, $otros_tributos, $total,
 *            $cae, $cae_vencimiento, $qr_src, $tipo_letra, $condicion_venta,
 *            $periodo_desde, $periodo_hasta, $fecha_vto_pago
 */
$issuer = $issuer ?? [];
$receiver = $receiver ?? [];
$comprobante = $comprobante ?? [];
$items = $items ?? [];
$subtotal = $subtotal ?? 0;
$iva_total = $iva_total ?? 0;
$otros_tributos = $otros_tributos ?? 0;
$total = $total ?? 0;
$cae = $cae ?? '';
$cae_vencimiento = $cae_vencimiento ?? '';
$qr_src = $qr_src ?? '';
$tipo_letra = $tipo_letra ?? 'B';
$condicion_venta = $condicion_venta ?? 'Efectivo';
$fecha = $comprobante['fecha'] ?? '';
$periodo_desde = $periodo_desde ?? $fecha;
$periodo_hasta = $periodo_hasta ?? $fecha;
$fecha_vto_pago = $fecha_vto_pago ?? $fecha;
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Factura</title>
	<style type="text/css">
		@page {
			size: A4;
			margin: 20mm;
		}
		* { box-sizing: border-box; }
		body {
			font-family: Arial, Helvetica, sans-serif;
			font-size: 13px;
			margin: 0;
			padding: 0;
			color: #000;
			width: 100%;
		}
		.bill-container {
			width: 100%;
			max-width: 170mm;
			margin-left: auto;
			margin-right: auto;
			border-collapse: collapse;
		}
		.bill-container td { vertical-align: top; }
		.bill-emitter-row td {
			border-bottom: 1px solid #000;
			padding: 10px 8px 10px 0;
		}
		.bill-emitter-row .col-emitter { width: 45%; text-align: left; }
		.bill-emitter-row .col-type { width: 10%; text-align: center; vertical-align: middle; }
		.bill-emitter-row .col-factura { width: 45%; text-align: left; padding-left: 12px; }
		.bill-type {
			width: 60px;
			height: 50px;
			margin: 0 auto;
			border: 1px solid #000;
			background-color: #fff;
			color: #000;
			text-align: center;
			font-size: 40px;
			font-weight: 600;
			line-height: 50px;
			display: table;
		}
		.bill-type span { display: table-cell; vertical-align: middle; }
		.text-lg { font-size: 30px; font-weight: bold; }
		.text-right { text-align: right; }
		.bill-row td { padding: 0; border: 0; }
		.bill-row td > .inner {
			border-top: 1px solid #000;
			border-bottom: 1px solid #000;
			padding: 10px 0 13px 0;
		}
		.row-details .inner,
		.row-qrcode .inner { border: 0; padding: 10px 0 0 0; }
		.row-details table { border-collapse: collapse; width: 100%; }
		.row-details table td {
			border: 1px solid #000;
			padding: 6px 8px;
			font-size: 13px;
		}
		.row-details table tr:first-child td {
			background-color: #c0c0c0;
			font-weight: bold;
			text-align: center;
			color: #000;
		}
		.row-details table tr:not(:first-child) td { border-top: 1px solid #c0c0c0; }
		.row-details table td.num,
		.row-details table tr:first-child td:nth-child(n+3) { text-align: right; }
		.row-details table tr:not(:first-child) td:nth-child(1),
		.row-details table tr:not(:first-child) td:nth-child(2) { text-align: left; }
		.total-row .inner {
			border-top: 2px solid #000;
			border-bottom: 2px solid #000;
			padding: 10px 0 13px 0;
		}
		.totals-table { width: 100%; border-collapse: collapse; }
		.totals-table td { padding: 4px 0; border: 0; }
		.totals-table .label { text-align: right; padding-right: 10px; }
		.totals-table .amount { text-align: right; font-weight: bold; width: 100px; }
		.row-qrcode td { padding: 10px 10px 10px 0; }
		.row-qrcode td:last-child { text-align: right; vertical-align: top; }
		.qr-cell img { width: 120px; height: 120px; display: block; }
		.margin-b-10 { margin-bottom: 10px; }
		.period-row { width: 100%; border-collapse: collapse; }
		.period-row td { padding: 2px 0; }
		.period-row td:first-child { text-align: left; }
		.period-row td:nth-child(2) { text-align: center; }
		.period-row td:last-child { text-align: right; }
		.recipient-row { width: 100%; border-collapse: collapse; }
		.recipient-row td { padding: 2px 0; }
		.recipient-row .c1 { width: 28%; }
		.recipient-row .c2 { width: 72%; }
		.recipient-row .half { width: 50%; }
		.invoice-details table { width: 100%; border-collapse: collapse; }
		.invoice-details td { padding: 2px 0; }
		.invoice-details .pv { width: 50%; }
		.invoice-details .cn { width: 50%; text-align: right; }
	</style>
</head>
<body>
	<table class="bill-container">
		<!-- Encabezado: emisor | caja B centrada | Factura + datos (todo dentro del área A4) -->
		<tr class="bill-emitter-row">
			<td class="col-emitter">
				<div class="text-lg"><?= htmlspecialchars($issuer['razon_social'] ?? '') ?></div>
				<p><strong>Razón social:</strong> <?= htmlspecialchars($issuer['razon_social'] ?? '') ?></p>
				<p><strong>Domicilio Comercial:</strong> <?= htmlspecialchars($issuer['domicilio'] ?? '') ?></p>
				<p><strong>Condición Frente al IVA:</strong> <?= htmlspecialchars($issuer['condicion_iva'] ?? 'Responsable inscripto') ?></p>
			</td>
			<td class="col-type">
				<div class="bill-type"><span><?= htmlspecialchars($tipo_letra) ?></span></div>
			</td>
			<td class="col-factura">
				<div class="text-lg">Factura</div>
				<table class="invoice-details">
					<tr>
						<td class="pv"><strong>Punto de Venta:</strong> <?= htmlspecialchars($comprobante['pto_vta'] ?? '') ?></td>
						<td class="cn"><strong>Comp. Nro:</strong> <?= htmlspecialchars($comprobante['nro'] ?? '') ?></td>
					</tr>
					<tr><td colspan="2"><strong>Fecha de Emisión:</strong> <?= htmlspecialchars($comprobante['fecha'] ?? '') ?></td></tr>
					<tr><td colspan="2"><strong>CUIT:</strong> <?= htmlspecialchars($issuer['cuit'] ?? '') ?></td></tr>
					<?php if (!empty($issuer['iibb'])): ?>
					<tr><td colspan="2"><strong>Ingresos Brutos:</strong> <?= htmlspecialchars($issuer['iibb']) ?></td></tr>
					<?php endif; ?>
					<?php if (!empty($issuer['inicio_actividad'])): ?>
					<tr><td colspan="2"><strong>Fecha de Inicio de Actividades:</strong> <?= htmlspecialchars($issuer['inicio_actividad']) ?></td></tr>
					<?php endif; ?>
				</table>
			</td>
		</tr>
		<!-- Período: Desde (izq) | Hasta (centro) | Fecha vto. pago (der) -->
		<tr class="bill-row">
			<td colspan="3">
				<div class="inner">
					<table class="period-row">
						<tr>
							<td style="width:33%;"><strong>Período Facturado Desde:</strong> <?= htmlspecialchars($periodo_desde) ?></td>
							<td style="width:34%;"><strong>Hasta:</strong> <?= htmlspecialchars($periodo_hasta) ?></td>
							<td style="width:33%;"><strong>Fecha de Vto. para el pago:</strong> <?= htmlspecialchars($fecha_vto_pago) ?></td>
						</tr>
					</table>
				</div>
			</td>
		</tr>
		<!-- Receptor -->
		<tr class="bill-row">
			<td colspan="3">
				<div class="inner">
					<table class="recipient-row">
						<tr>
							<td class="c1"><strong>CUIL/CUIT:</strong></td>
							<td class="c2"><?= htmlspecialchars($receiver['nro_doc'] ?? '0') ?></td>
						</tr>
						<tr>
							<td class="c1"><strong>Apellido y Nombre / Razón social:</strong></td>
							<td class="c2"><?= htmlspecialchars($receiver['nombre'] ?? 'Consumidor Final') ?></td>
						</tr>
						<tr>
							<td class="half"><strong>Condición Frente al IVA:</strong> <?= htmlspecialchars($receiver['condicion_iva'] ?? 'Consumidor final') ?></td>
							<td class="half"><strong>Domicilio:</strong> <?= htmlspecialchars($receiver['domicilio'] ?? '-') ?></td>
						</tr>
						<tr>
							<td colspan="2"><strong>Condicion de venta:</strong> <?= htmlspecialchars($condicion_venta) ?></td>
						</tr>
					</table>
				</div>
			</td>
		</tr>
		<!-- Tabla de ítems -->
		<tr class="bill-row row-details">
			<td colspan="3">
				<div class="inner">
					<table>
						<tr>
							<td>Código</td>
							<td>Producto / Servicio</td>
							<td class="num">Cantidad</td>
							<td class="num">U. Medida</td>
							<td class="num">Precio Unit.</td>
							<td class="num">% Bonif.</td>
							<td class="num">Imp. Bonif.</td>
							<td class="num">Subtotal</td>
						</tr>
						<?php foreach ($items as $item):
							$cant = (float) ($item['quantity'] ?? $item['cantidad'] ?? 1);
							$pu = (float) ($item['unitPrice'] ?? $item['precio_unitario'] ?? 0);
							$st = isset($item['subtotal']) ? (float) $item['subtotal'] : ($cant * $pu);
						?>
						<tr>
							<td><?= htmlspecialchars($item['code'] ?? $item['codigo'] ?? '-') ?></td>
							<td><?= htmlspecialchars($item['description'] ?? $item['descripcion'] ?? '') ?></td>
							<td class="num"><?= number_format($cant, 2, ',', '.') ?></td>
							<td class="num"><?= htmlspecialchars($item['unit'] ?? 'Unidad') ?></td>
							<td class="num"><?= number_format($pu, 2, ',', '.') ?></td>
							<td class="num">0,00</td>
							<td class="num">0,00</td>
							<td class="num"><?= number_format($st, 2, ',', '.') ?></td>
						</tr>
						<?php endforeach; ?>
					</table>
				</div>
			</td>
		</tr>
		<!-- Totales -->
		<tr class="bill-row total-row">
			<td colspan="3">
				<div class="inner">
					<table class="totals-table">
						<tr>
							<td class="label"><strong>Subtotal: $</strong></td>
							<td class="amount"><strong><?= number_format((float) $subtotal, 2, ',', '.') ?></strong></td>
						</tr>
						<tr>
							<td class="label"><strong>Importe Otros Tributos: $</strong></td>
							<td class="amount"><strong><?= number_format((float) $otros_tributos, 2, ',', '.') ?></strong></td>
						</tr>
						<tr>
							<td class="label"><strong>Importe total: $</strong></td>
							<td class="amount"><strong><?= number_format((float) $total, 2, ',', '.') ?></strong></td>
						</tr>
					</table>
				</div>
			</td>
		</tr>
		<!-- QR (izq) | CAE (der) -->
		<tr class="bill-row row-qrcode">
			<td class="qr-cell">
				<?php if ($qr_src !== ''): ?>
				<img id="qrcode" src="<?= htmlspecialchars($qr_src) ?>" alt="QR AFIP" width="120" height="120" />
				<?php endif; ?>
			</td>
			<td></td>
			<td>
				<div class="text-right margin-b-10"><strong>CAE N°:&nbsp;</strong><?= htmlspecialchars($cae) ?></div>
				<div class="text-right"><strong>Fecha de Vto. de CAE:&nbsp;</strong><?= htmlspecialchars($cae_vencimiento) ?></div>
			</td>
		</tr>
	</table>
</body>
</html>
