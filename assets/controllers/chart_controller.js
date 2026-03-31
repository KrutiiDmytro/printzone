import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.element.addEventListener('chartjs:pre-connect', this._onPreConnect);
        this.element.addEventListener('chartjs:connect', this._onConnect);
    }

    disconnect() {
        // Удаляем слушатели при отключении контроллера
        this.element.removeEventListener('chartjs:pre-connect', this._onPreConnect);
        this.element.removeEventListener('chartjs:connect', this._onConnect);
    }

    _onPreConnect(event) {
        // График еще не создан
        // Можно получить доступ к конфигурации, которая будет передана в "new Chart()"
        console.log('Chart config:', event.detail.config);

        // Форматируем значения на оси Y как валюту (EUR - евро)
        // Чтобы не перезаписать существующую конфигурацию, проверяем наличие scales
        if (!event.detail.config.options.scales) {
            event.detail.config.options.scales = {};
        }
        if (!event.detail.config.options.scales.y) {
            event.detail.config.options.scales.y = {};
        }
        event.detail.config.options.scales.y.ticks = {
            callback: function (value) {
                return new Intl.NumberFormat('de-DE', {
                    style: 'currency',
                    currency: 'EUR'
                }).format(value);
            }
        };
    }

    _onConnect(event) {
        // График только что создан
        console.log('Chart created:', event.detail.chart);

        // Изменяем курсор при наведении на точки графика
        event.detail.chart.options.onHover = (mouseEvent, activeElements) => {
            if (activeElements.length > 0) {
                event.detail.chart.canvas.style.cursor = 'pointer';
            } else {
                event.detail.chart.canvas.style.cursor = 'default';
            }
        };

        // Обработка клика по графику
        event.detail.chart.options.onClick = (mouseEvent, activeElements) => {
            if (activeElements.length > 0) {
                const datasetIndex = activeElements[0].datasetIndex;
                const index = activeElements[0].index;
                const value = event.detail.chart.data.datasets[datasetIndex].data[index];
                console.log('Clicked value:', value);
            }
        };
    }
}