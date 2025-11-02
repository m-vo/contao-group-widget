import { Controller } from "@hotwired/stimulus";

export default class GroupWidgetController extends Controller {
  static targets = ["element", "addButton", "order", "footerDropArea"];
  static values = {
    min: Number,
    max: Number,
    orderingEnabled: Boolean,
  };

  #submitForm = () => {};
  #elements = [];

  connect() {
    this.#submitForm = () => this.element.closest("form").submit();
    this.#elements = this.elementTargets;

    this.#updateElementStates();
  }

  up(event) {
    const position = this.#getPosition(this.#getCurrentElement(event.target));

    this.#swap(position, position - 1);

    event.target.blur();
  }

  down(event) {
    const position = this.#getPosition(this.#getCurrentElement(event.target));

    this.#swap(position, position + 1);

    event.target.blur();
  }

  dragStart(event) {
    const element = this.#getCurrentElement(event.target);
    const dropArea = this.#getDropAreaForElement(element);

    event.dataTransfer.setData(
      "text/plain",
      this.#getPosition(element).toString(),
    );
    dropArea.classList.add("drag");
  }

  dragEnd(event) {
    const dropArea = this.#getDropAreaForElement(
      this.#getCurrentElement(event.target),
    );

    dropArea.classList.remove("drag");
  }

  dragEnter(event) {
    event.target.classList.add("dropping");
  }

  dragOver(event) {
    // prevented via Stimulus modifier
  }

  dragLeave(event) {
    event.target.classList.remove("dropping");
  }

  drop(event) {
    event.target.classList.remove("dropping");

    this.#move(
      Number.parseInt(event.dataTransfer.getData("text/plain")),
      event.target === this.footerDropAreaTarget
        ? this.#elements.length
        : this.#getPosition(this.#getCurrentElement(event.target)),
    );
  }

  remove(event) {
    const position = this.#getPosition(this.#getCurrentElement(event.target));

    this.#elements[position].remove();
    this.#elements.splice(position, 1);
    this.#updateElementStates();

    event.target.blur();
  }

  add() {
    const scrollOffset =
      this.addButtonTarget.getBoundingClientRect().y + window.scrollY;
    window.sessionStorage.setItem(
      "contao_backend_offset",
      scrollOffset.toString(),
    );

    this.#updateOrderTarget(true);
    this.#submitForm();
  }

  #getPosition(element) {
    return Number.parseInt(element.style.order);
  }

  #setPosition(element, position) {
    element.style.order = position.toString();
  }

  #toggleAttribute(attribute, el, state) {
    if (state) {
      el.setAttribute(attribute, "true");

      return;
    }

    el.removeAttribute(attribute);
  }

  #getCurrentElement(control) {
    return control.closest('[data-mvo--group-widget-target="element"]');
  }

  #getDropAreaForElement(element) {
    return element.querySelector(".drop-area");
  }

  #updateElementStates() {
    const numElements = this.#elements.length;

    this.#elements.forEach((element, index) => {
      this.#setPosition(element, index);

      const up = element.querySelector("button[data-up]");
      const down = element.querySelector("button[data-down]");
      const remove = element.querySelector("button[data-remove]");
      const drag = element.querySelector("*[data-drag]");

      this.#toggleAttribute(
        "disabled",
        up,
        !this.orderingEnabledValue || 0 === index,
      );
      this.#toggleAttribute(
        "disabled",
        down,
        !this.orderingEnabledValue || numElements - 1 === index,
      );
      this.#toggleAttribute(
        "disabled",
        remove,
        !isNaN(this.minValue) && numElements === this.minValue,
      );

      const allowDrag = this.orderingEnabledValue && numElements > 1;
      this.#toggleAttribute("draggable", drag, allowDrag);
      drag.classList.toggle("disabled", !allowDrag);
    });

    this.#toggleAttribute(
      "disabled",
      this.addButtonTarget,
      !isNaN(this.maxValue) && numElements === this.maxValue,
    );

    this.#updateOrderTarget();
  }

  #updateOrderTarget(insertNew = false) {
    const indices = this.#elements.map((el) =>
      Number.parseInt(el.getAttribute("data-id")),
    );

    if (insertNew) {
      indices.push(-1);
    }

    this.orderTarget.value = indices.join(",");
  }

  #swap(a, b) {
    [this.#elements[a], this.#elements[b]] = [
      this.#elements[b],
      this.#elements[a],
    ];

    this.#updateElementStates();
  }

  #move(from, to) {
    // An elements own drag handle is above, so dragging to the next index
    // is essentially staying in place. We adjust the target accordingly
    // when dragging down.
    if (to > from) {
      to--;
    }

    if (from === to) {
      return;
    }

    this.#elements.splice(to, 0, this.#elements.splice(from, 1)[0]);

    this.#updateElementStates();
  }
}
