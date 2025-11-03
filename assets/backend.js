import "./styles.pcss";

import { Application } from "@hotwired/stimulus";
import GroupWidgetController from "./controller";

const application = Application.start();
application.register("mvo--group-widget", GroupWidgetController);
