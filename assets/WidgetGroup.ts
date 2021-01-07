import './WidgetGroup.scss'

export class WidgetGroup {
    private readonly elements: HTMLElement[]
    private readonly footerContainer: HTMLElement;
    private readonly orderField: HTMLInputElement;

    private readonly min: number;
    private readonly max: number;

    constructor(container: HTMLElement) {
        const elementsContainer = container.querySelector('.widget-group--container');
        this.min = Number.parseInt(elementsContainer.getAttribute('data-min'));
        this.max = Number.parseInt(elementsContainer.getAttribute('data-max'));
        this.elements = Array.from(elementsContainer.querySelectorAll('.widget-group--element'));

        this.footerContainer = container.querySelector('.widget-group--footer .controls');

        this.orderField = this.footerContainer.querySelector('input[data-order]');
    }

    private static setPosition(el: HTMLElement, position: number): void {
        el.style.order = position.toString();
    }

    private static getPosition(el: HTMLElement): number {
        return Number.parseInt(el.style.order);
    }

    private static setState(el: HTMLElement, state: boolean): void {
        if (state) {
            el.removeAttribute('disabled');

            return;
        }

        el.setAttribute('disabled', 'disabled');
    }

    init(): void {
        this.updateElementStates();

        this.elements.forEach(el => {
            const [up, down, remove, add] = this.getButtons(el);

            up.addEventListener('click', event => {
                event.preventDefault();

                const position = WidgetGroup.getPosition(el);
                this.swap(position, position - 1);
            });

            down.addEventListener('click', event => {
                event.preventDefault();

                const position = WidgetGroup.getPosition(el);
                this.swap(position, position + 1);
            });

            remove.addEventListener('click', event => {
                event.preventDefault();

                const position = WidgetGroup.getPosition(el);
                this.remove(position);
            });

            add.addEventListener('click', event => {
                event.preventDefault();

                this.updateOrderTarget(true);
                el.closest('form').submit();
            });
        })
    }

    private getButtons(el: HTMLElement): [HTMLElement, HTMLElement, HTMLElement, HTMLElement] {
        return [
            el.querySelector('button[data-up]'),
            el.querySelector('button[data-down]'),
            el.querySelector('button[data-remove]'),
            this.footerContainer.querySelector('button[data-add]'),
        ];
    }

    private updateElementStates(): void {
        this.elements.forEach((el, index) => {
            WidgetGroup.setPosition(el, index);

            const [up, down, remove, add] = this.getButtons(el);
            const numElements = this.elements.length;

            WidgetGroup.setState(up, 0 !== index);
            WidgetGroup.setState(down, numElements - 1 !== index);
            WidgetGroup.setState(remove, isNaN(this.min) || numElements !== this.min);
            WidgetGroup.setState(add, isNaN(this.max) || numElements !== this.max);
        });

        this.updateOrderTarget();
    }

    private updateOrderTarget(insertNew: boolean = false): void {
        const indices = this.elements.map(el => Number.parseInt(el.getAttribute('data-id')));

        if(insertNew) {
            indices.push(0);
        }

        this.orderField.value = indices.join(',');
    }

    private swap(a: number, b: number) {
        [this.elements[a], this.elements[b]] = [this.elements[b], this.elements[a]];

        this.updateElementStates();
    }

    private remove(position: number) {
        this.elements[position].remove();
        this.elements.splice(position, 1);

        this.updateElementStates();
    }
}