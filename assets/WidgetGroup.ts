import './WidgetGroup.scss'

export class WidgetGroup {
    private readonly form: HTMLFormElement;

    private readonly elements: HTMLElement[]
    private readonly min: number;
    private readonly max: number;

    // Footer elements
    private readonly orderField: HTMLInputElement;
    private readonly addButton: HTMLElement;
    private readonly footerDropArea: HTMLElement;

    constructor(container: HTMLElement) {
        this.form = container.closest('form');

        const elementsContainer = container.querySelector('.widget-group--container');
        this.min = Number.parseInt(elementsContainer.getAttribute('data-min'));
        this.max = Number.parseInt(elementsContainer.getAttribute('data-max'));
        this.elements = Array.from(elementsContainer.querySelectorAll('.widget-group--element'));

        const footerContainer = container.querySelector('.widget-group--footer');
        this.orderField = footerContainer.querySelector('input[data-order]');
        this.addButton =  footerContainer.querySelector('button[data-add]');
        this.footerDropArea = footerContainer.querySelector<HTMLElement>('.drop-area');
    }

    private static setPosition(el: HTMLElement, position: number): void {
        el.style.order = position.toString();
    }

    private static getPosition(el: HTMLElement): number {
        return Number.parseInt(el.style.order);
    }

    private static toggleAttribute(attribute: string, el: HTMLElement, state: boolean): void {
        if (state) {
            el.setAttribute(attribute, 'true');

            return;
        }

        el.removeAttribute(attribute);
    }

    private static getControls(el: HTMLElement): [HTMLElement, HTMLElement, HTMLElement, HTMLElement] {
        return [
            el.querySelector('button[data-up]'),
            el.querySelector('button[data-down]'),
            el.querySelector('button[data-remove]'),
            el.querySelector('*[data-drag]'),
        ];
    }

    init(): void {
        this.updateElementStates();

        this.elements.forEach(el => {
            const [up, down, remove, drag] = WidgetGroup.getControls(el);

            // Move one with arrow buttons
            up.addEventListener('click', event => {
                event.preventDefault();

                const position = WidgetGroup.getPosition(el);
                this.swap(position, position - 1);
                up.blur();
            });

            down.addEventListener('click', event => {
                event.preventDefault();

                const position = WidgetGroup.getPosition(el);
                this.swap(position, position + 1);
                down.blur();
            });

            // Delete
            remove.addEventListener('click', event => {
                event.preventDefault();

                const position = WidgetGroup.getPosition(el);
                this.remove(position);
                remove.blur();
            });

            // Drag & drop
            drag.addEventListener('dragstart', event => {
                event.dataTransfer.setData('text/plain', WidgetGroup.getPosition(el).toString());

                el.classList.add('drag');
            });

            drag.addEventListener('dragend', event => {
                el.classList.remove('drag');
            });

            this.makeDroppable(
                el.querySelector<HTMLElement>('.drop-area'),
                event => {
                    this.move(
                        Number.parseInt(event.dataTransfer.getData('text/plain')),
                        WidgetGroup.getPosition(el)
                    );
                }
            );
        })

        this.makeDroppable(
            this.footerDropArea,
            event => {
                this.move(
                    Number.parseInt(event.dataTransfer.getData('text/plain')),
                    this.elements.length
                );
            }
        );

        // Add
        this.addButton.addEventListener('click', event => {
            event.preventDefault();

            this.updateOrderTarget(true);
            this.form.submit();
        });
    }

    private updateElementStates(): void {
        const numElements = this.elements.length;

        this.elements.forEach((el, index) => {
            WidgetGroup.setPosition(el, index);

            const [up, down, remove, drag] = WidgetGroup.getControls(el);

            WidgetGroup.toggleAttribute('disabled', up, 0 === index);
            WidgetGroup.toggleAttribute('disabled', down, numElements - 1 === index);
            console.log(numElements, this.min);
            WidgetGroup.toggleAttribute('disabled', remove, isNaN(this.min) || numElements === this.min);

            const allowDrag = numElements > 1;
            WidgetGroup.toggleAttribute('draggable', drag, allowDrag);
            drag.classList.toggle('disabled', !allowDrag);

        });

        WidgetGroup.toggleAttribute('disabled', this.addButton, isNaN(this.max) || numElements === this.max);

        this.updateOrderTarget();
    }

    private updateOrderTarget(insertNew: boolean = false): void {
        const indices = this.elements.map(el => Number.parseInt(el.getAttribute('data-id')));

        if (insertNew) {
            indices.push(-1);
        }

        this.orderField.value = indices.join(',');
    }

    private makeDroppable(el: HTMLElement, onDrop: (DragEvent) => any): void {
        el.addEventListener('dragenter', () => {
            el.classList.add('dropping');
        });

        el.addEventListener('dragleave', () => {
            el.classList.remove('dropping');
        });

        el.addEventListener('dragover', event => {
            event.preventDefault();
        });

        el.addEventListener('drop', event => {
            el.classList.remove('dropping');

            onDrop(event);
        });
    }

    private swap(a: number, b: number) {
        [this.elements[a], this.elements[b]] = [this.elements[b], this.elements[a]];

        this.updateElementStates();
    }

    private move(from: number, to: number) {
        // An elements own drag handle is above, so dragging to the next index
        // is essentially staying in place. We adjust the target accordingly
        // when dragging down.
        if (to > from) {
            to--;
        }

        if (from === to) {
            return;
        }

        this.elements.splice(to, 0, this.elements.splice(from, 1)[0])

        this.updateElementStates();
    }

    private remove(position: number) {
        this.elements[position].remove();
        this.elements.splice(position, 1);

        this.updateElementStates();
    }
}