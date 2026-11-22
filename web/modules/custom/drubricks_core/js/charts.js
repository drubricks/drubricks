/**
 * @file
 * Chart.js charts (contrib "charts") in the UI kit look.
 *
 * ChartHooks gives the datasets kit colour tokens ("teal", or "var(--teal)")
 * and leaves out Charts' fixed colours and fonts. Here, a Chart.js plugin
 * turns the tokens into the page's colours on every update, and the chart
 * defaults (font, text, grid lines, tooltip) come from the kit's CSS
 * variables. When the colour scheme or palette changes (data-* attributes of
 * <html>), the charts redraw in the new colours; charts in a swapped-out
 * fragment (HTMX) are destroyed.
 */
((Drupal, Chart) => {
  if (!Chart) return;

  const TOKENS = ['blue', 'teal', 'green', 'yellow', 'orange', 'red', 'pink', 'purple', 'indigo', 'gray'];
  const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

  /** A kit token ("teal", "var(--teal)") as a colour; anything else as is. */
  const resolve = (value) => {
    if (Array.isArray(value)) return value.map(resolve);
    if (typeof value !== 'string') return value;
    const variable = value.match(/^var\((--[\w-]+)\)$/)?.[1] ?? (TOKENS.includes(value) ? `--${value}` : null);
    return variable ? css(variable) || value : value;
  };

  /** Chart defaults from the kit: font, text, grid lines, tooltip. */
  const defaults = () => {
    const d = Chart.defaults;
    d.font.family = css('--font') || d.font.family;
    d.font.size = 12;
    d.color = css('--label-2');
    d.borderColor = css('--sep');
    d.scale.grid.color = css('--sep');
    d.plugins.title.color = css('--label');
    d.plugins.title.font = { size: 13, weight: '600' };
    d.plugins.legend.labels.usePointStyle = true;
    d.plugins.legend.labels.pointStyle = 'rectRounded';
    Object.assign(d.plugins.tooltip, {
      backgroundColor: css('--bg-menu'),
      titleColor: css('--label'),
      bodyColor: css('--label-2'),
      borderColor: css('--sep'),
      borderWidth: 1,
      padding: 8,
      cornerRadius: 8,
      usePointStyle: true,
    });
    d.elements.bar.borderRadius = 4;
    d.elements.bar.borderSkipped = 'start';
    d.datasets.bar.maxBarThickness = 36;
  };

  /** The datasets in the current colours (they keep their tokens in _kit). */
  const paintData = (chart) => {
    chart.data.datasets.forEach((dataset) => {
      dataset._kit ??= { backgroundColor: dataset.backgroundColor, borderColor: dataset.borderColor };
      dataset.backgroundColor = resolve(dataset._kit.backgroundColor);
      dataset.borderColor = resolve(dataset._kit.borderColor);
    });
  };

  /** A drawn chart in the current colours: data, axes, legend, tooltip. */
  const repaint = (chart) => {
    const d = Chart.defaults;
    const { options } = chart;
    paintData(chart);
    Object.values(options.scales || {}).forEach((scale) => {
      scale.ticks.color = d.color;
      scale.grid.color = d.scale.grid.color;
      scale.border.color = d.scale.grid.color;
      scale.title.color = d.color;
    });
    options.plugins.legend.labels.color = d.color;
    options.plugins.title.color = d.plugins.title.color;
    ['backgroundColor', 'titleColor', 'bodyColor', 'borderColor'].forEach((key) => {
      options.plugins.tooltip[key] = d.plugins.tooltip[key];
    });
    // Element options (bar colours) are cached per dataset: resolve again.
    chart.config.clearCache();
    chart.update();
  };

  Chart.register({ id: 'drubricksKit', beforeInit: paintData });
  defaults();

  // Light / dark / palette switch: new defaults, every chart repainted.
  new MutationObserver(() => {
    defaults();
    Object.values(Chart.instances).forEach(repaint);
  }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-scheme', 'data-palette', 'class', 'style'] });

  Drupal.behaviors.drubricksCharts = {
    detach(context, settings, trigger) {
      if (trigger !== 'unload') return;
      context.querySelectorAll?.('canvas').forEach((canvas) => Chart.getChart(canvas)?.destroy());
    },
  };
})(Drupal, window.Chart);
