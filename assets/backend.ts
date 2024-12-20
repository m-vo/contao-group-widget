import { WidgetGroup } from "./WidgetGroup";

function setupWidgetGroups() {
    document.querySelectorAll<HTMLElement>('.group-widget').forEach(el => {
        new WidgetGroup(el).init();
    });
}

document.addEventListener('DOMContentLoaded', setupWidgetGroups);
document.addEventListener('turbo:render', setupWidgetGroups);
